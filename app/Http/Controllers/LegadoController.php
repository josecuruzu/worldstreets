<?php

namespace App\Http\Controllers;

use App\Models\Ciudad;
use App\Models\Pais;
use App\Models\Provincia;
use App\Support\Idioma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/*
 * Las URLs del sitio viejo en CakePHP.
 *
 * La migración no convive: el Laravel reemplaza al CakePHP entero, en la misma
 * carpeta y el mismo dominio. El día del corte el Cake deja de existir, así que
 * cualquier URL vieja que no tenga destino acá es un 404 inmediato para Google,
 * sin fallback posible. Por eso esto va antes del corte, no después.
 *
 * Esquema viejo, sacado de config/routes.php del Cake:
 *
 *   /view/find/maps/address/{ciudades.url}        ficha de ciudad
 *   /country/maps/address/{paises.url}            país
 *   /state/maps/address/{provincias.url}          provincia
 *   /county/maps/address/{departamentos.url}      condado
 *   /continent/maps/address/{continentes.url}     continente
 *   /{esp|por|fra|ita}/...                        las mismas, por idioma
 *
 * Medido contra las exportaciones de Search Console (1.920 URLs con datos y
 * 6.930 clics en 16 meses): TODAS caen en ese esquema, ninguna quedó fuera
 * pese al `fallbacks(DashedRoute::class)` que el Cake tenía al final.
 *
 * Los slugs no hay que recalcularlos: están guardados en la columna `url` de
 * cada tabla, que es la misma que usaba el Cake para armar los enlaces. Contra
 * los slugs con tráfico, países y provincias resuelven al 100%.
 */
class LegadoController extends Controller
{
    /** Los cuatro idiomas del sitio viejo. Todos van a la versión en inglés. */
    public const IDIOMAS = 'esp|por|fra|ita';

    /**
     * El slug de la URL, leído por nombre y no por posición.
     *
     * Las mismas cinco rutas se declaran dos veces, con y sin el prefijo de
     * idioma. Con el prefijo, el primer parámetro posicional es el idioma, así
     * que un método que reciba `string $slug` termina buscando "esp" en la base
     * y devolviendo 410 sin que nada lo delate.
     */
    private function slug(Request $peticion): string
    {
        return (string) $peticion->route('slug');
    }

    /**
     * Ficha de ciudad: /view/find/maps/address/cape-town-western-cape-south-africa
     *
     * El slug de ciudad es ambiguo —868 slugs con tráfico matchean 996 filas—,
     * así que hace falta una regla de desempate. De los 65 casos ambiguos, el
     * patrón es siempre el mismo: hay una ciudad real y uno o más duplicados de
     * GeoNames con población 0 (Shenzhen 10.358.381 contra 0, Barcelona 424.795
     * contra 0). Gana la de más población, y a igualdad el id más chico, que es
     * el que servía el Cake: su consulta no tenía ORDER BY y el índice sobre
     * `url` devuelve las filas ordenadas por (url, id).
     */
    public function ciudad(Request $peticion): RedirectResponse
    {
        $slug = $this->slug($peticion);

        $ciudad = Ciudad::query()
            ->where('url', $slug)
            ->orderByDesc('population')
            ->orderBy('id')
            ->with(['pais', 'provincia'])
            ->first();

        $this->conservarElEspanol($peticion, $ciudad?->pais?->id);

        $destino = $ciudad?->rutaDeLaFicha();

        if ($destino === null) {
            return $this->sinDestino();
        }

        return redirect($destino, 301);
    }

    /**
     * País: /country/maps/address/south-africa
     *
     * `paises.url` es la misma columna que usa el esquema nuevo, así que el
     * destino es el slug tal cual. Se consulta igual en vez de redirigir a
     * ciegas: un slug que ya no existe tiene que dar 410, no un 404 de Laravel.
     */
    public function pais(Request $peticion): RedirectResponse
    {
        $slug = $this->slug($peticion);

        $pais = Pais::byUrl($slug)->first(['id', 'url']);

        if ($pais === null) {
            return $this->sinDestino();
        }

        $this->conservarElEspanol($peticion, $pais->id);

        return redirect()->to(Idioma::url('pais', [$pais->url]), 301);
    }

    /**
     * Provincia: /state/maps/address/western-cape-south-africa
     *
     * El slug nuevo no es el viejo: sale de `Str::slug(provincias.nombre)` y
     * cuelga del país, mientras que el viejo traía la jerarquía entera pegada.
     */
    public function provincia(Request $peticion): RedirectResponse
    {
        $slug = $this->slug($peticion);

        $provincia = Provincia::query()->where('url', $slug)->with('pais')->first();

        if ($provincia?->pais?->url === null) {
            return $this->sinDestino();
        }

        $this->conservarElEspanol($peticion, $provincia->pais->id);

        return redirect()->to(Idioma::url('provincia', [$provincia->pais->url, $provincia->slug]), 301);
    }

    /**
     * Una URL `/esp/...` aterriza en la versión en español, no en la inglesa.
     *
     * Es la parte cara de este controlador. **El 35,8% de los clics del sitio
     * entra hoy por una URL `/esp/`** —2.479 de 6.930— y esas páginas rankean
     * en posición 6-12 para consultas en español porque su título coincide con
     * la consulta: `calles de marianao` contra "Calles de Marianao". Mandarlas
     * todas a la ficha inglesa conserva el 301 y el contenido, y tira a la
     * basura la coincidencia, que es lo único que las hace rankear.
     *
     * Solo se conserva el español donde hay versión en español. Un
     * `/esp/view/find/maps/address/calgary-alberta-canada` —que existe, con 46
     * impresiones— va a la ficha inglesa, porque Canadá no está entre los 21
     * países que publicamos en español y una ficha en español de Calgary no
     * existe.
     *
     * Los otros tres idiomas del Cake no tienen adónde ir: `/fra/` son 401
     * clics, `/ita/` 63 y `/por/` 46, contra 2.479 del español. Siguen cayendo
     * en la versión inglesa a propósito.
     */
    private function conservarElEspanol(Request $peticion, ?string $paisId): void
    {
        if ($peticion->route('idioma') === 'esp' && Idioma::cubre($paisId)) {
            App::setLocale(Idioma::ESPANOL);
        }
    }

    /**
     * Condado y continente: /county/... y /continent/...
     *
     * No tienen equivalente en el esquema nuevo, que son países, provincias y
     * ciudades con calles. Van a 410 y no a 404 por la misma razón que el resto
     * de lo que se elimina: un 410 le dice a Google que la página se fue a
     * propósito y acelera la limpieza, que es justamente el problema del sitio
     * (18,28 M de URLs descubiertas contra 26.335 indexadas).
     *
     * Los condados no tienen una sola URL con clics. Los continentes tienen 5
     * slugs y 47 clics, casi todos de `europe`: si se decide recuperarlos,
     * alcanza con una ficha de continente que liste sus países, que son 7 URLs
     * más y no mueve el espacio de URLs.
     */
    public function eliminado(): never
    {
        abort(410);
    }

    /**
     * Un slug del esquema viejo que ya no resuelve a nada.
     *
     * 410 y no 404: el 404 le dice a Google "no está, volvé a probar"; el 410,
     * "no está y no vuelve". Con 240 rastreos diarios contra 18 M de URLs
     * conocidas, esa diferencia es la que decide cuánto tarda la limpieza.
     */
    private function sinDestino(): never
    {
        abort(410);
    }
}
