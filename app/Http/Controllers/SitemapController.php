<?php

namespace App\Http\Controllers;

use App\Models\Ciudad;
use App\Models\Pais;
use App\Models\Provincia;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/*
 * Los sitemaps.
 *
 * El sitio viejo publica 21.140 URLs —5 de portada, 30 continentes, 1.255
 * países y 19.850 provincias— y **ninguna ciudad**, que es justo donde está el
 * contenido: 704 fichas de ciudad se llevaron 3.684 de los 6.930 clics de los
 * últimos 16 meses sin estar declaradas en ningún lado.
 *
 * Acá se declara exactamente el espacio de URLs que la regla de
 * ciudad-con-contenido dejó en pie, ni una más:
 *
 *     ciudades con calles   18.359
 *     provincias             1.956
 *     países                   207
 *
 * Solo van las provincias y los países que tienen al menos una ciudad con
 * página. Los demás siguen respondiendo 200 —son navegables— pero declarar en
 * un sitemap una lista vacía es pedirle a un rastreador con 240 peticiones
 * diarias que gaste una en nada.
 *
 * ── Por qué está partido por país y no por provincia ──────────────────────
 *
 * El plan decía por provincia. Medido: 1.956 provincias tienen ciudades con
 * página, así que serían 1.956 archivos, y el mayor tendría 1.839 URLs. Por
 * país son 207 archivos y el mayor —Canadá, 4.045— sigue estando dos órdenes
 * de magnitud por debajo del límite de 50.000 por sitemap. Menos archivos que
 * descubrir, y ninguno cerca del techo.
 */
class SitemapController extends Controller
{
    /** Doce horas: la lista se mueve cuando alguien busca una calle nueva. */
    private const HORAS_DE_CACHE = 12;

    /**
     * El índice: un archivo de lugares y uno de ciudades por país.
     */
    public function index(): Response
    {
        $paises = Cache::remember('sitemap.paises', now()->addHours(self::HORAS_DE_CACHE), fn () => $this->paisesConCiudades());

        $mapas = $paises
            ->map(fn (string $url) => route('sitemap.pais', $url))
            ->prepend(route('sitemap.lugares'));

        return $this->xml(view('sitemaps.indice', ['mapas' => $mapas]));
    }

    /**
     * Países y provincias. Van juntos porque son 2.163 URLs en total: partirlos
     * sería un archivo más que descubrir para nada.
     */
    public function lugares(): Response
    {
        $urls = Cache::remember('sitemap.lugares', now()->addHours(self::HORAS_DE_CACHE), function () {
            $paises = $this->paisesConCiudades();

            $provincias = Provincia::query()
                ->whereIn('id', $this->provinciasConCiudades())
                ->with('pais:id,url')
                ->get(['id', 'nombre', 'pais_id'])
                ->filter(fn (Provincia $provincia) => $provincia->pais?->url !== null)
                ->map(fn (Provincia $provincia) => route('provincia', [$provincia->pais->url, $provincia->slug]));

            /*
             * `unknown-region` no es una fila de `provincias`: es el cajón de
             * las 56.761 ciudades cuyo admin1 es el centinela `XX.00` de
             * GeoNames. Tiene página propia y ciudades adentro, así que si
             * quedara fuera sus ciudades no tendrían quién las enlace desde acá.
             */
            return $paises
                ->map(fn (string $url) => route('pais', $url))
                ->merge($provincias)
                ->merge($this->regionesDesconocidas())
                ->values();
        });

        return $this->xml(view('sitemaps.urls', ['urls' => $urls]));
    }

    /**
     * Las ciudades con página de un país.
     */
    public function pais(string $pais): Response
    {
        $modelo = Pais::byUrl($pais)->firstOrFail(['id', 'url']);

        $urls = Cache::remember(
            "sitemap.ciudades.{$modelo->id}",
            now()->addHours(self::HORAS_DE_CACHE),
            fn () => Ciudad::query()
                ->where('pais_id', $modelo->id)
                ->conCalles()
                ->with('provincia:id,nombre')
                ->orderByDesc('population')
                ->get(['id', 'nombre', 'alias', 'pais_id', 'provincia_id'])
                ->map(fn (Ciudad $ciudad) => route('ciudad', [$modelo->url, $ciudad->provincia_slug, $ciudad->segmento]))
                ->values()
        );

        abort_if($urls->isEmpty(), 404);

        return $this->xml(view('sitemaps.urls', ['urls' => $urls]));
    }

    /**
     * Los países que tienen al menos una ciudad con página, por su slug.
     *
     * @return Collection<int, string>
     */
    private function paisesConCiudades(): Collection
    {
        $ids = Ciudad::query()->conCalles()->distinct()->pluck('pais_id');

        return Pais::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->pluck('url')
            ->filter()
            ->values();
    }

    /**
     * Los ids de provincia que tienen al menos una ciudad con página.
     *
     * @return Collection<int, string>
     */
    private function provinciasConCiudades(): Collection
    {
        return Ciudad::query()->conCalles()->distinct()->pluck('provincia_id');
    }

    /**
     * Las URLs de `unknown-region` que tienen ciudades con página.
     *
     * @return Collection<int, string>
     */
    private function regionesDesconocidas(): Collection
    {
        $paises = Ciudad::query()
            ->conCalles()
            ->whereRaw("provincia_id LIKE '%.00'")
            ->distinct()
            ->pluck('pais_id');

        return Pais::query()
            ->whereIn('id', $paises)
            ->pluck('url')
            ->filter()
            ->map(fn (string $url) => route('provincia', [$url, Ciudad::PROVINCIA_DESCONOCIDA]))
            ->values();
    }

    private function xml(mixed $vista): Response
    {
        return response($vista->render(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
