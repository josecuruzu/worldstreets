<?php

namespace App\Http\Controllers;

use App\Models\Busqueda;
use App\Models\Ciudad;
use App\Models\Pais;
use App\Models\Provincia;
use App\Support\Canonico;
use App\Support\Idioma;
use App\Support\PrecargaDelMapa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
    /**
     * Ciudades por página en la ficha de provincia.
     *
     * 300 y no 60: con 60, Nova Scotia (1.837 fichas) eran ~31 páginas y a las
     * últimas solo se llegaba pasándolas de a una, o sea a decenas de clics de
     * la home. Con 300 son 7 y la paginación numerada las enlaza todas, así que
     * cualquier ficha queda a 2-3 clics. Ninguna provincia pasa de ~1.900.
     */
    private const POR_PAGINA = 300;

    /** Otras ciudades de la región que se enlazan al pie de una ficha. */
    private const VECINAS = 6;

    /** Ciudades destacadas en la ficha de país. */
    private const DESTACADAS = 12;

    public function pais(string $pais): View
    {
        $pais = Pais::byUrl($pais)->with('continente')->firstOrFail();

        $this->soloDondeHayVersion($pais->id);

        /*
         * Una sola consulta para toda la cobertura del país: calles por ciudad,
         * y de ahí se derivan en PHP el total, las ciudades con lista, el
         * reparto por provincia y las mejor cubiertas. Para Canadá son 4.045
         * filas —285 ms— y antes hacían falta dos pasadas sobre `busquedas`
         * para sacar menos información.
         */
        $cobertura = Cache::remember(
            "lugar.pais.{$pais->id}.cobertura.v2",
            now()->addDay(),
            fn () => $this->coberturaDelPais($pais)
        );

        $provincias = Cache::remember(
            "lugar.pais.{$pais->id}.provincias.v2",
            now()->addDay(),
            fn () => $this->provinciasConSusCiudades($pais, $cobertura['callesPorProvincia'])
        );

        return view('lugares.pais', [
            'pais' => $pais,
            'provincias' => $provincias,
            'ciudadesConCalles' => $cobertura['ciudadesConCalles'],
            'callesDelPais' => $cobertura['callesDelPais'],
            /*
             * Las ciudades se ordenan por CANTIDAD DE CALLES y no por
             * población, que es lo que hacían antes.
             *
             * El visitante que llega acá desde `reverse address lookup canada`
             * viene a encontrar una dirección, y para eso una ciudad con 8.014
             * calles le sirve y una más poblada con 3 no. Toronto tiene más
             * gente que Calgary; Calgary tiene la lista.
             */
            'destacadas' => $this->ciudadesPorCobertura($cobertura['mejorCubiertas']),
            'alternos' => Idioma::alternos('pais', [$pais->url], $pais->id),
        ]);
    }

    /**
     * La cobertura real del país, en una consulta.
     *
     * Son las cifras que le dicen al visitante si su lugar va a aparecer: el
     * dataset tiene 4.697.141 ciudades y 18.359 con alguna calle, así que
     * "13.247 ciudades" sin más contexto promete lo que no hay.
     *
     * Guarda ids y números, nunca modelos: un modelo en la caché queda viejo y
     * pesa varias veces más que su id.
     *
     * @return array{
     *     ciudadesConCalles: int,
     *     callesDelPais: int,
     *     callesPorProvincia: array<string, int>,
     *     mejorCubiertas: list<array{id: int, calles: int}>
     * }
     */
    private function coberturaDelPais(Pais $pais): array
    {
        /*
         * Primero las ciudades del país que tienen calles, y recién después el
         * conteo. El orden importa y es lo que hace barata la consulta.
         *
         * Antes esto era un solo JOIN filtrado por `pais_id`, y ahí MySQL
         * elegía recorrer TODAS las ciudades del país para ir a buscar sus
         * calles: 102.328 filas en Japón, de las que solo 224 tienen alguna.
         * El filtro que faltaba es `tiene_calles`, que ya está adentro del
         * índice `ciudades_pais_poblacion` (pais_id, tiene_calles, population):
         * con él la primera consulta devuelve las 224 directas.
         *
         * Medido contra la base real, con el buffer pool caliente:
         *
         *     Japón     400 ms → 77 ms
         *     Canadá    457 ms → 217 ms   (4.045 ciudades, el peor caso)
         *     Austria   161 ms → 30 ms
         *
         * Forzar el orden del join con STRAIGHT_JOIN también ayuda, pero la
         * mitad: 177 ms en Japón. Dos consultas le ganan a una porque la
         * segunda ya no tiene que arrastrar `provincia_id` por el GROUP BY.
         *
         * `tiene_calles` es exactamente "tiene filas en busquedas" —
         * verificado contra la base: cero ciudades descuadradas en cualquiera
         * de los dos sentidos— y lo mantiene `BuscadorController::direccion()`
         * en cada calle nueva. Además es la misma columna con la que se
         * deciden el sitemap y el 410 de la regla de ciudad-con-contenido, así
         * que la ficha de país ahora cuenta lo mismo que declara el sitemap.
         */
        $provinciaPorCiudad = DB::table('ciudades')
            ->where('pais_id', $pais->id)
            ->where('tiene_calles', true)
            ->pluck('provincia_id', 'id');

        if ($provinciaPorCiudad->isEmpty()) {
            return [
                'ciudadesConCalles' => 0,
                'callesDelPais' => 0,
                'callesPorProvincia' => [],
                'mejorCubiertas' => [],
            ];
        }

        $porCiudad = DB::table('busquedas')
            ->whereIn('ciudad_id', $provinciaPorCiudad->keys())
            ->selectRaw('ciudad_id, count(*) as calles')
            ->groupBy('ciudad_id')
            ->orderByDesc('calles')
            ->get();

        return [
            'ciudadesConCalles' => $porCiudad->count(),
            'callesDelPais' => (int) $porCiudad->sum('calles'),
            'callesPorProvincia' => $porCiudad
                ->groupBy(fn (object $fila) => $provinciaPorCiudad[$fila->ciudad_id] ?? null)
                ->map(fn ($filas) => (int) $filas->sum('calles'))
                ->all(),
            'mejorCubiertas' => $porCiudad
                ->take(self::DESTACADAS)
                ->map(fn (object $fila) => ['id' => (int) $fila->ciudad_id, 'calles' => (int) $fila->calles])
                ->values()
                ->all(),
        ];
    }

    /**
     * Hidrata las ciudades mejor cubiertas conservando ese orden.
     *
     * `whereIn` devuelve lo que el motor quiera, así que el orden se rearma acá
     * con el de la lista que vino de la consulta de cobertura.
     *
     * @param  list<array{id: int, calles: int}>  $mejores
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function ciudadesPorCobertura(array $mejores): Collection
    {
        if ($mejores === []) {
            return collect();
        }

        $ciudades = Ciudad::query()
            ->whereIn('id', array_column($mejores, 'id'))
            // La provincia va en la URL de cada ficha, así que se trae de una
            // en vez de una consulta por ciudad.
            ->with('provincia:id,nombre')
            ->get(['id', 'nombre', 'alias', 'population', 'provincia_id', 'fcode'])
            ->keyBy('id');

        return collect($mejores)
            ->filter(fn (array $fila) => $ciudades->has($fila['id']))
            ->map(fn (array $fila) => (object) [
                'ciudad' => $ciudades[$fila['id']],
                'calles' => $fila['calles'],
            ])
            ->values();
    }

    public function provincia(string $pais, string $provincia): View
    {
        $pais = Pais::byUrl($pais)->firstOrFail();

        $this->soloDondeHayVersion($pais->id);

        $provincia = $this->buscarProvincia($pais, $provincia);

        $ciudades = Ciudad::query()
            ->where('provincia_id', $provincia?->id ?? "{$pais->id}.00")
            ->conCalles()
            ->orderByDesc('population')
            ->orderBy('nombre')
            // paginate y no simplePaginate: la lista ya es solo de ciudades con
            // calles (máx. ~1.900 por provincia) y el índice
            // (provincia_id, tiene_calles, population) resuelve el conteo sin
            // tocar la tabla. Hace falta el total para enlazar todas las páginas.
            ->paginate(self::POR_PAGINA, ['id', 'nombre', 'alias', 'population', 'fcode']);

        /*
         * Una página fuera de rango es 404, no un 200 vacío. Antes
         * `/canada/ontario?page=8` respondía 200 con canónica propia y cero
         * ciudades: un espacio infinito de páginas casi idénticas, y Google ya
         * conoce URLs `?page=N`. La página 1 nunca falla: una región sin
         * ciudades con calles sigue siendo parte de la jerarquía.
         */
        if ($ciudades->currentPage() > 1 && $ciudades->currentPage() > $ciudades->lastPage()) {
            abort(404);
        }

        return view('lugares.provincia', [
            'pais' => $pais,
            'provincia' => $provincia,
            'nombreProvincia' => $provincia?->nombre ?: __('Unknown region'),
            'slugProvincia' => $provincia?->slug ?? Ciudad::PROVINCIA_DESCONOCIDA,
            'ciudades' => $ciudades,
            'alternos' => Idioma::alternos(
                'provincia',
                [$pais->url, $provincia?->slug ?? Ciudad::PROVINCIA_DESCONOCIDA],
                $pais->id
            ),
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

        $this->soloDondeHayVersion($modelo->pais?->id);

        // El 301 del slug tiene que quedarse en el idioma en el que se pidió:
        // mandar /es/... a la versión inglesa perdería el idioma en el camino.
        if ($modelo->segmento !== $ciudad) {
            return redirect()->to(Idioma::url('ciudad', [$pais, $provincia, $modelo->segmento]), 301);
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

        return $this->ficha($modelo);
    }

    /**
     * La ficha de una ciudad ya resuelta.
     *
     * Está separada de `ciudad()` porque hay dos caminos que llegan a la misma
     * página: la URL normal, y `/r/{codigo}` —el enlace corto del botón de
     * compartir—, que renderiza esta vista en vez de redirigir para que la
     * dirección no aparezca en la barra del navegador. Ver
     * `CompartirController`.
     *
     * @param  array{calle: string, altura: string}|null  $direccionInicial
     *                                                                       la dirección que la página tiene que buscar sola al abrirse
     */
    public function ficha(Ciudad $modelo, ?array $direccionInicial = null): View
    {
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
            // v2: la clave cambia porque el índice cambió de forma — ahora
            // agrupa por tipo de vía en los países hispanohablantes.
            "lugar.ciudad.{$modelo->id}.calles.v2",
            now()->addHours(12),
            fn () => Busqueda::indiceDeCiudad($modelo->id, $modelo->pais?->id)
        );

        // El total real, no el que se muestra: cada letra lista hasta
        // CALLES_POR_LETRA pero sabe cuántas tiene.
        $total = $indice->sum(fn (array $grupo) => $grupo['total']);

        /*
         * Otras ciudades de la misma región, para que la ficha no sea un
         * callejón sin salida. Solo las que tienen página: enlazar a una que
         * responde 410 manda al visitante y al rastreador contra una pared.
         */
        $vecinas = Cache::remember(
            // v2: antes eran las más pobladas de la región; ahora las más cercanas.
            "lugar.ciudad.{$modelo->id}.vecinas.v2",
            now()->addDay(),
            fn () => $this->ciudadesCercanas($modelo)
        );

        return view('lugares.ciudad', [
            'ciudad' => $modelo,
            'indice' => $indice,
            'totalCalles' => $total,
            'vecinas' => $vecinas,
            'nombresLocales' => $modelo->nombresLocales(),
            /*
             * En la página de un enlace compartido no van hreflang: la pareja
             * de idiomas es de la ficha, no de `/r/{codigo}`, que no se
             * indexa en ninguno de los dos.
             */
            'alternos' => $direccionInicial === null
                ? Idioma::alternos(
                    'ciudad',
                    [$modelo->pais?->url, $modelo->provincia_slug, $modelo->segmento],
                    $modelo->pais?->id
                )
                : [],
            'canonical' => $direccionInicial === null
                ? null
                : Canonico::url(Idioma::url('ciudad', [
                    $modelo->pais?->url, $modelo->provincia_slug, $modelo->segmento,
                ])),
            /*
             * La dirección que llega por un enlace compartido. La vista la
             * pasa al componente Alpine, que la busca al montarse.
             *
             * Y `compartido` marca la página para que salga con `noindex` y
             * con el canonical apuntando a la ficha: `/r/{codigo}` no es una
             * URL del sitio, es un mensaje de WhatsApp que resultó ser una
             * página.
             */
            /*
             * El tile del centro del mapa, para precargarlo desde la cabecera.
             * Es el LCP de esta página y el navegador no lo descubre hasta que
             * Leaflet se montó; ver `PrecargaDelMapa::tileCentral`. Se calcula
             * acá y no en la vista porque es aritmética, no presentación.
             */
            'tilePrecargado' => PrecargaDelMapa::tileCentral(
                (float) $modelo->centro_x,
                (float) $modelo->centro_y,
                (int) $modelo->zoom,
            ),
            'direccionInicial' => $direccionInicial,
            'compartido' => $direccionInicial !== null,
        ]);
    }

    /**
     * Las ciudades con página más cercanas, dentro de la misma región.
     *
     * Antes se enlazaban las más pobladas, que son las mismas para todas las
     * fichas de la región: las 6 grandes recibían todos los enlaces y el resto
     * de las fichas no recibía ninguno. Por cercanía cada ficha enlaza a sus
     * vecinas de verdad, y los enlaces se reparten por toda la región, que es
     * lo que le da a Google un camino hacia las fichas profundas.
     *
     * `centro_x` es la latitud y `centro_y` la longitud. La distancia es
     * euclidiana sobre grados, con la longitud escalada por cos(latitud): no es
     * geodésica, pero para ordenar vecinas dentro de una provincia alcanza. Se
     * calcula en SQL sobre ≤ ~1.900 filas y se cachea un día.
     *
     * Sin coordenadas, cae al orden de antes: por población.
     *
     * @return \Illuminate\Support\Collection<int, Ciudad>
     */
    private function ciudadesCercanas(Ciudad $ciudad): Collection
    {
        $consulta = Ciudad::query()
            ->where('provincia_id', $ciudad->provincia_id)
            ->whereKeyNot($ciudad->id)
            ->conCalles();

        $columnas = ['id', 'nombre', 'alias', 'provincia_id'];

        if ($ciudad->centro_x === null || $ciudad->centro_y === null) {
            return $consulta->orderByDesc('population')->limit(self::VECINAS)->get($columnas);
        }

        $lat = (float) $ciudad->centro_x;
        $lon = (float) $ciudad->centro_y;
        $escala = cos(deg2rad($lat)) ** 2;

        return $consulta
            ->whereNotNull('centro_x')
            ->whereNotNull('centro_y')
            ->orderByRaw(
                '((centro_x - ?) * (centro_x - ?)) + ((centro_y - ?) * (centro_y - ?) * ?)',
                [$lat, $lat, $lon, $lon, $escala]
            )
            ->orderBy('id')
            ->limit(self::VECINAS)
            ->get($columnas);
    }

    /**
     * En español, solo los países que tienen versión en español.
     *
     * Sin esto, `/es/japan` devolvería la ficha de Japón con los textos de la
     * plantilla traducidos y el contenido igual: una copia del corpus entero
     * en otra URL, que es exactamente lo que dejó al sitio con 18,25 M de URLs
     * y una penalización algorítmica. La versión en español existe para los 21
     * países hispanohablantes y para nadie más.
     *
     * 404 y no 301 a la inglesa: un 301 invitaría a Google a descubrir y
     * seguir 22.581 URLs en español que no queremos que existan.
     */
    private function soloDondeHayVersion(?string $paisId): void
    {
        if (Idioma::esEspanol() && ! Idioma::cubre($paisId)) {
            abort(404);
        }
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
    /**
     * @param  array<string, int>  $callesPorProvincia
     */
    private function provinciasConSusCiudades(Pais $pais, array $callesPorProvincia = []): array
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
                // Las calles son el número que contesta la pregunta con la
                // que el visitante llega: "¿va a estar mi dirección acá?".
                'calles' => (int) ($callesPorProvincia[$provincia->id] ?? 0),
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
                'calles' => (int) ($callesPorProvincia["{$pais->id}.00"] ?? 0),
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
