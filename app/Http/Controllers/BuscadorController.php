<?php

namespace App\Http\Controllers;

use App\Models\Busqueda;
use App\Models\Ciudad;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Endpoints JSON del buscador de la home.
 *
 * La búsqueda es en dos pasos porque así están modelados los datos: primero
 * se resuelve la ciudad (tabla ciudades) y después la calle dentro de ella
 * (tabla busquedas, que es el histórico de calles que la gente buscó).
 */
class BuscadorController extends Controller
{
    /** Con menos caracteres el resultado es demasiado ruidoso para servir de algo. */
    private const MIN_CARACTERES = 2;

    /**
     * Autocomplete de ciudades.
     *
     * GET /api/ciudades?q=cordo
     */
    public function ciudades(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (Str::length($q) < self::MIN_CARACTERES) {
            return response()->json([]);
        }

        $clave = 'buscador.ciudades.'.md5(Ciudad::normalizar($q));

        $ciudades = Cache::remember($clave, now()->addMinutes(15), function () use ($q) {
            return Ciudad::buscar($q)
                ->with(['provincia:id,nombre', 'pais:id,nombre'])
                ->limit(8)
                ->get([
                    'id', 'nombre', 'alias', 'url', 'centro_x', 'centro_y', 'zoom',
                    'visitas', 'fcode', 'provincia_id', 'pais_id',
                ])
                ->map(fn (Ciudad $c) => [
                    'id' => $c->id,
                    'nombre' => $c->display_name,
                    'provincia' => $c->provincia?->nombre,
                    'pais' => $c->pais?->nombre,
                    'url' => $c->url,
                    'lat' => (float) $c->centro_x,
                    'lon' => (float) $c->centro_y,
                    'zoom' => $c->zoom,
                    'visitas' => $c->visitas,
                    // PPLC = capital de país, PPLA = capital de provincia.
                    'capital' => in_array($c->fcode, ['PPLC', 'PPLA'], true),
                ])
                ->values();
        });

        return response()->json($ciudades);
    }

    /**
     * Calles de una ciudad. Sin q devuelve las más buscadas; con q, las que
     * coinciden.
     *
     * GET /api/ciudades/{ciudad}/calles?q=san+mart
     */
    public function calles(Request $request, Ciudad $ciudad): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $clave = self::claveCalles($ciudad->id, $q);

        $calles = Cache::remember($clave, now()->addMinutes(15), function () use ($ciudad, $q) {
            $query = $q === ''
                ? Busqueda::topDeCiudad($ciudad->id)
                : Busqueda::where('ciudad_id', $ciudad->id)
                    // Se compara contra la versión sin espacios para que
                    // "sanmartin" y "san martin" den lo mismo.
                    ->where('nombre_calle_compacto', 'like', '%'.addcslashes(Busqueda::compactar($q), '%_\\').'%')
                    ->orderByDesc('cantidad_busquedas')
                    ->limit(10);

            return $query->get(['id', 'nombre_calle', 'cantidad_busquedas'])
                ->map(fn (Busqueda $b) => [
                    'nombre' => $b->nombre_calle,
                    'busquedas' => $b->cantidad_busquedas,
                ])
                ->values();
        });

        return response()->json([
            'ciudad' => [
                'id' => $ciudad->id,
                'nombre' => $ciudad->display_name,
                'provincia' => $ciudad->provincia?->nombre,
                'pais' => $ciudad->pais?->nombre,
                'lat' => (float) $ciudad->centro_x,
                'lon' => (float) $ciudad->centro_y,
                'zoom' => $ciudad->zoom,
                'total_calles' => $ciudad->busquedas()->count(),
            ],
            'calles' => $calles,
        ]);
    }

    /**
     * Registra que alguien buscó una calle: suma uno a su contador, o la da de
     * alta si es la primera vez. Es lo que mantiene vivo el ranking que sirve
     * `calles()`; hasta ahora la tabla solo tenía lo que vino del import.
     *
     * Guarda el nombre de la calle y la ciudad, nada más: ni IP, ni usuario,
     * ni la altura que el visitante haya tipeado.
     *
     * POST /api/busquedas
     */
    public function registrar(Request $request): Response
    {
        $datos = $request->validate([
            'ciudad_id' => ['required', 'integer', 'exists:ciudades,id'],
            'nombre_calle' => ['required', 'string', 'max:255'],
        ]);

        // Los datos importados usan el guion como separador en algunas calles.
        $nombreCalle = trim(str_replace('-', ' ', $datos['nombre_calle']));
        $compacto = Busqueda::compactar($nombreCalle);

        if ($compacto === '') {
            return response()->noContent();
        }

        $ciudadId = (int) $datos['ciudad_id'];

        /*
         * Deduplicación por sesión: si el visitante vuelve sobre la misma calle
         * de la misma ciudad, no cuenta de nuevo. Sin esto cada tecleo o cada
         * F5 inflaría el ranking.
         */
        $claveSesion = "busqueda.{$ciudadId}.".md5($compacto);

        if ($request->session()->has($claveSesion)) {
            return response()->noContent();
        }

        $request->session()->put($claveSesion, true);

        DB::transaction(function () use ($ciudadId, $nombreCalle, $compacto) {
            $existente = fn () => Busqueda::where('ciudad_id', $ciudadId)
                ->where('nombre_calle_compacto', $compacto);

            if ($busqueda = $existente()->first()) {
                $busqueda->increment('cantidad_busquedas');
            } else {
                try {
                    Busqueda::create([
                        'ciudad_id' => $ciudadId,
                        'nombre_calle' => $nombreCalle,
                        'nombre_calle_compacto' => $compacto,
                        'cantidad_busquedas' => 1,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    /*
                     * Otra petición dio de alta la misma calle entre el select
                     * y el insert. Con el índice único (2026_09_11_110000) eso
                     * ahora es un error en vez de una fila duplicada, así que
                     * se resuelve como lo que era: una búsqueda más.
                     */
                    $existente()->increment('cantidad_busquedas');
                }
            }

            Ciudad::whereKey($ciudadId)->increment('visitas');
        });

        /*
         * El ranking que se acaba de mover es el que devuelve `calles()` sin
         * término, así que esa entrada queda vieja. Las de búsquedas con
         * término no se pueden invalidar: son una por término tipeado y el
         * store `database` no soporta tags. Vencen solas a los 15 minutos.
         */
        Cache::forget(self::claveCalles($ciudadId, ''));

        return response()->noContent();
    }

    /** Clave de caché del listado de calles. Compartida para que no se desfase. */
    private static function claveCalles(int $ciudadId, string $q): string
    {
        return "buscador.calles.{$ciudadId}.".md5($q);
    }
}
