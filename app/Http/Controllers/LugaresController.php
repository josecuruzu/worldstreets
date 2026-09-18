<?php

namespace App\Http\Controllers;

use App\Models\Busqueda;
use App\Models\Ciudad;
use App\Models\Pais;
use App\Models\Provincia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/*
 * Las fichas de lugar: país, provincia y ciudad.
 *
 * Son la parte enlazable del sitio. Hasta acá los 4,7 M de ciudades vivían
 * detrás de un único buscador en `/`: una sola URL para todo el planeta, nada
 * que un buscador pudiera recorrer ni que alguien pudiera compartir.
 *
 * Las rutas son jerárquicas — /japan/tokyo/tokyo-1850147 — y la ficha de ciudad
 * se resuelve **por id**, no por slug. No es un capricho: los slugs de ciudad
 * se repiten en 433.217 grupos (hay 578 "Krajan" en East Java), así que el
 * slug solo no identifica nada. Va en la URL igual, por legibilidad, y si no
 * coincide con el canónico se redirige con un 301.
 */
class LugaresController extends Controller
{
    /** Ciudades por página en la ficha de provincia. */
    private const POR_PAGINA = 60;

    /** Ciudades destacadas en la ficha de país. */
    private const DESTACADAS = 12;

    public function pais(string $pais): View
    {
        $pais = Pais::byUrl($pais)->with('continente')->firstOrFail();

        $provincias = Cache::remember(
            "lugar.pais.{$pais->id}.provincias",
            now()->addDay(),
            fn () => $this->provinciasConSusCiudades($pais)
        );

        $destacadas = Ciudad::query()
            ->where('pais_id', $pais->id)
            // Solo las que tienen página: enlazar a una ficha que responde 410
            // es mandar al visitante y al rastreador contra una pared.
            ->conCalles()
            // La provincia va en la URL de cada ficha, así que se trae de una
            // en vez de una consulta por ciudad.
            ->with('provincia:id,nombre')
            ->orderByDesc('population')
            ->limit(self::DESTACADAS)
            ->get(['id', 'nombre', 'alias', 'population', 'provincia_id', 'fcode']);

        return view('lugares.pais', [
            'pais' => $pais,
            'provincias' => $provincias,
            'destacadas' => $destacadas,
        ]);
    }

    public function provincia(string $pais, string $provincia): View
    {
        $pais = Pais::byUrl($pais)->firstOrFail();
        $provincia = $this->buscarProvincia($pais, $provincia);

        $ciudades = Ciudad::query()
            ->where('provincia_id', $provincia?->id ?? "{$pais->id}.00")
            ->conCalles()
            ->orderByDesc('population')
            ->orderBy('nombre')
            // simplePaginate y no paginate: contar las 155.023 ciudades de la
            // provincia más grande en cada visita no aporta nada a la página.
            ->simplePaginate(self::POR_PAGINA, ['id', 'nombre', 'alias', 'population', 'fcode']);

        return view('lugares.provincia', [
            'pais' => $pais,
            'provincia' => $provincia,
            'nombreProvincia' => $provincia?->nombre ?: __('Unknown region'),
            'slugProvincia' => $provincia?->slug ?? Ciudad::PROVINCIA_DESCONOCIDA,
            'ciudades' => $ciudades,
        ]);
    }

    public function ciudad(string $pais, string $provincia, string $ciudad): View|RedirectResponse
    {
        // El id es lo que identifica la ficha; el slug de adelante es adorno.
        if (! preg_match('/(?:^|-)(\d+)$/', $ciudad, $partes)) {
            abort(404);
        }

        $modelo = Ciudad::with(['pais', 'provincia'])->findOrFail((int) $partes[1]);

        // La ciudad tiene que colgar de la jerarquía que dice la URL, para que
        // no haya una ficha distinta por cada camino que lleve al mismo id.
        if ($modelo->pais?->url !== $pais || $modelo->provincia_slug !== $provincia) {
            abort(404);
        }

        if ($modelo->segmento !== $ciudad) {
            return redirect()->route('ciudad', [$pais, $provincia, $modelo->segmento], 301);
        }

        /*
         * Una ciudad tiene página cuando alguien buscó una calle ahí. Son
         * 18.361 de 4.697.141: el resto es un punto en el mapa, y publicarlas
         * todas es lo que dejó al sitio con 18.283.514 URLs descubiertas contra
         * 26.335 indexadas.
         *
         * 410 y no 404 ni `noindex`: con 240 rastreos diarios, un noindex que
         * Google tiene que venir a leer no llega nunca. El 410 dice que se fue a
         * propósito y acelera la limpieza. Reemplaza al `noindex,follow` que
         * traía la vista, que era demasiado permisivo para un sitio degradado.
         *
         * Medido contra Search Console: de las 868 fichas de ciudad con tráfico,
         * 86 caen acá y son 116 de 3.684 clics — el 3,1%. Es el precio de bajar
         * de 18,25 M de URLs a ~23.000.
         *
         * No es definitivo: la primera vez que alguien busque una calle acá, la
         * página vuelve a existir sola.
         */
        if (! $modelo->tiene_calles) {
            abort(410);
        }

        /*
         * El índice A–Z es la jugada de contenido del proyecto: una ficha con
         * las calles que la gente buscó acá es una página sustancial, y sale
         * sin crear una sola URL nueva.
         *
         * Va cacheado porque es la misma consulta para todos y no cambia de
         * una visita a la otra: la lista solo se mueve cuando alguien busca una
         * calle que no estaba.
         */
        $indice = Cache::remember(
            "lugar.ciudad.{$modelo->id}.calles",
            now()->addHours(12),
            fn () => Busqueda::indiceDeCiudad($modelo->id)
        );

        // El total real, no el que se muestra: cada letra lista hasta
        // CALLES_POR_LETRA pero sabe cuántas tiene.
        $total = $indice->sum(fn (array $grupo) => $grupo['total']);

        return view('lugares.ciudad', [
            'ciudad' => $modelo,
            'indice' => $indice,
            'totalCalles' => $total,
            'nombresLocales' => $modelo->nombresLocales(),
        ]);
    }

    /**
     * Las provincias del país con cuántas ciudades tiene cada una.
     *
     * El conteo se agrupa por `provincia_id` y no se filtra por `pais_id`, que
     * sería lo natural: filtrando por país, la base tiene que ir a buscar el
     * `provincia_id` de cada ciudad a la tabla —700.000 veces en China— y la
     * página tardaba 9 segundos. Pidiéndole las provincias por su id, el índice
     * `ciudades_provincia_poblacion` ya trae todo lo que hace falta y no toca
     * la tabla.
     *
     * @return list<array{nombre: string, slug: string, ciudades: int}>
     */
    private function provinciasConSusCiudades(Pais $pais): array
    {
        $provincias = $pais->provincias()->orderBy('nombre')->get();

        $ciudadesPorProvincia = Ciudad::query()
            ->whereIn('provincia_id', $provincias->pluck('id'))
            // El número que se muestra al lado de cada provincia tiene que ser
            // el de ciudades que se pueden visitar, no el del dataset: decir
            // "155.023 ciudades" y listar 48 es prometer lo que no hay.
            ->conCalles()
            ->selectRaw('provincia_id, count(*) as total')
            ->groupBy('provincia_id')
            ->pluck('total', 'provincia_id');

        $listado = $provincias
            ->map(fn (Provincia $provincia) => [
                'nombre' => $provincia->nombre ?: $provincia->id,
                'slug' => $provincia->slug,
                'ciudades' => (int) ($ciudadesPorProvincia[$provincia->id] ?? 0),
            ])
            ->all();

        /*
         * Las ciudades que apuntan al centinela `XX.00` sin que exista la fila
         * de provincia quedarían fuera de la jerarquía, sin ninguna página que
         * las enlace. Se las agrupa en su propio cajón para que se puedan
         * recorrer igual.
         */
        $sinProvincia = $provincias->contains('id', "{$pais->id}.00")
            ? 0
            : Ciudad::where('provincia_id', "{$pais->id}.00")->conCalles()->count();

        if ($sinProvincia > 0) {
            $listado[] = [
                'nombre' => 'Unknown region',
                'slug' => Ciudad::PROVINCIA_DESCONOCIDA,
                'ciudades' => $sinProvincia,
            ];
        }

        return $listado;
    }

    /**
     * La provincia dentro del país, por su slug.
     *
     * Devuelve null para `unknown-region`, que es el cajón de las 56.761
     * ciudades cuyo admin1 es el centinela `XX.00` de GeoNames y por lo tanto
     * no tienen provincia de verdad — Mónaco es una de ellas.
     *
     * Busca comparando slugs en PHP y no en SQL porque el slug se calcula a
     * partir del nombre y no está en ninguna columna; como ningún país pasa de
     * 212 provincias, traerlas todas sale más barato que indexar una columna
     * nueva.
     */
    private function buscarProvincia(Pais $pais, string $slug): ?Provincia
    {
        if ($slug === Ciudad::PROVINCIA_DESCONOCIDA) {
            return null;
        }

        $provincia = $pais->provincias->first(fn (Provincia $provincia) => $provincia->slug === $slug);

        return $provincia ?? abort(404);
    }
}
