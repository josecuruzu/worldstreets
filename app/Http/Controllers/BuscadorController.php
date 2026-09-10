<?php

namespace App\Http\Controllers;

use App\Models\Busqueda;
use App\Models\Ciudad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $clave = "buscador.calles.{$ciudad->id}.".md5($q);

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
}
