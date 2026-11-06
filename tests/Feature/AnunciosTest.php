<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los bloques de AdSense.
 *
 * Esta clase existe por dos errores que ya se cometieron en calles-argentinas
 * —mismo dueño, misma monetización— y que tienen en común que **no dan ningún
 * error visible**: la página carga, no hay nada rojo en la consola, y los
 * ingresos se caen.
 *
 * 1. Dos `adsbygoogle.js` en la misma página. Pasó cuando el dueño pegó un
 *    bloque a mano encima del que ya ponía el layout. Resultado medido en
 *    producción: `window.adsbygoogle.length === 4` con la cola sin procesar y
 *    todos los <ins> en 0×0. Cero anuncios.
 * 2. El `push()` diferido con requestIdleCallback. Los anuncios servidos por
 *    página cayeron de 3,2 a 1,0 y el RPM de $2,54 a $0,60.
 *
 * Y uno propio de la ficha de ciudad: el bloque de arriba tiene que ser de
 * tamaño fijo. Con `auto` y full-width-responsive AdSense puede servir un
 * 336×280 donde se esperaba un banner y empujar el buscador —que es la función
 * del sitio— fuera de la primera pantalla del teléfono.
 */
class AnunciosTest extends TestCase
{
    use RefreshDatabase;

    private const FICHA = '/canada/alberta/calgary-5913490';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('continentes')->insert([
            'id' => 'NA', 'nombre' => 'North America', 'url' => 'north-america',
            'centro_x' => 45, 'centro_y' => -100, 'zoom' => 3, 'descripcion' => '',
        ]);
        DB::table('paises')->insert([
            'id' => 'CA', 'nombre' => 'Canada', 'url' => 'canada', 'continente_id' => 'NA',
            'centro_x' => 56, 'centro_y' => -106, 'zoom' => 4,
        ]);
        DB::table('provincias')->insert([
            'id' => 'CA.01', 'nombre' => 'Alberta', 'url' => 'alberta', 'pais_id' => 'CA',
            'centro_x' => 55, 'centro_y' => -115,
        ]);
        DB::table('ciudades')->insert([
            'id' => 5913490,
            'nombre' => 'Calgary', 'nombre_min' => 'calgary', 'url' => 'calgary',
            'fcode' => 'PPLA', 'population' => 1019942,
            'pais_id' => 'CA', 'provincia_id' => 'CA.01', 'departamento_id' => 'CA.01.1',
            'centro_x' => 51.05, 'centro_y' => -114.08,
            'limite_sw_x' => 50.7, 'limite_sw_y' => -114.6,
            'limite_ne_x' => 51.36, 'limite_ne_y' => -113.83,
            'zoom' => 13, 'visitas' => 0, 'tiene_calles' => true,
        ]);
        DB::table('busquedas')->insert([
            'ciudad_id' => 5913490,
            'nombre_calle' => 'macleod trail se', 'nombre_calle_compacto' => 'macleodtrailse',
            'cantidad_busquedas' => 3,
        ]);
    }

    private const FICHA_PAIS = '/canada';

    private function configurarAnuncios(): void
    {
        config([
            'services.adsense.client' => 'ca-pub-0000000000000000',
            'services.adsense.slots.ciudad_superior' => '1111111111',
            'services.adsense.slots.ciudad_medio' => '2222222222',
            'services.adsense.slots.ciudad_final' => '3333333333',
            'services.adsense.slots.ciudad_indice' => '4444444444',
            'services.adsense.slots.pais_superior' => '5555555555',
            'services.adsense.slots.pais_medio' => '6666666666',
            'services.adsense.indice_cada' => 8,
            // En 0 para que la regla de las fichas chicas no afecte a los
            // demás casos; los suyos la fijan en 10.
            'services.adsense.minimo_calles' => 0,
        ]);
    }

    /**
     * Una ficha con pocas calles es casi una página vacía: lleva solo la
     * unidad final, no las tres. El sitio viene de una penalización
     * algorítmica y un mapa con dos anuncios arriba es la señal equivocada.
     */
    public function test_una_ficha_con_pocas_calles_lleva_solo_la_unidad_final(): void
    {
        $this->configurarAnuncios();
        config(['services.adsense.minimo_calles' => 10]);

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        preg_match_all('/data-ad-slot="(\d+)"/', $html, $encontrados);
        $this->assertSame(['3333333333'], $encontrados[1]);
        $this->assertSame(1, substr_count($html, 'adsbygoogle = window.adsbygoogle'));
    }

    /** En el límite: 10 calles ya llevan las tres, 9 todavía no. */
    public function test_el_limite_de_calles_es_inclusivo(): void
    {
        $this->configurarAnuncios();
        config(['services.adsense.minimo_calles' => 10]);

        // El setUp ya crea 1 calle: con 8 más son 9.
        foreach (range(1, 8) as $i) {
            DB::table('busquedas')->insert([
                'ciudad_id' => 5913490, 'nombre_calle' => "calle {$i}",
                'nombre_calle_compacto' => "calle{$i}", 'cantidad_busquedas' => 1,
            ]);
        }
        $nueve = $this->get(self::FICHA)->assertOk()->getContent();
        $this->assertStringNotContainsString('data-ad-slot="1111111111"', $nueve);

        DB::table('busquedas')->insert([
            'ciudad_id' => 5913490, 'nombre_calle' => 'calle 9',
            'nombre_calle_compacto' => 'calle9', 'cantidad_busquedas' => 1,
        ]);
        \Illuminate\Support\Facades\Cache::flush();
        $diez = $this->get(self::FICHA)->assertOk()->getContent();
        $this->assertStringContainsString('data-ad-slot="1111111111"', $diez);
        $this->assertStringContainsString('data-ad-slot="2222222222"', $diez);
        $this->assertStringContainsString('data-ad-slot="3333333333"', $diez);
    }

    /** Una ficha con contenido de verdad conserva el reparto de siempre. */
    public function test_una_ficha_con_muchas_calles_conserva_las_tres_unidades(): void
    {
        $this->configurarAnuncios();
        config(['services.adsense.minimo_calles' => 10]);
        $this->llenarElIndice(); // 27 calles

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        $this->assertStringContainsString('data-ad-slot="1111111111"', $html);
        $this->assertStringContainsString('data-ad-slot="2222222222"', $html);
        $this->assertStringContainsString('data-ad-slot="3333333333"', $html);
    }

    /**
     * Una calle por letra, para que el índice tenga 26 grupos como los tiene
     * una ciudad real grande. Con una sola calle —lo que arma el setUp— el
     * índice tiene un grupo y no se intercala nada, que es justamente el
     * comportamiento que hay que distinguir.
     */
    private function llenarElIndice(): void
    {
        foreach (range('a', 'z') as $letra) {
            DB::table('busquedas')->insert([
                'ciudad_id' => 5913490,
                'nombre_calle' => $letra.'ldergrove ave',
                'nombre_calle_compacto' => $letra.'ldergroveave',
                'cantidad_busquedas' => 1,
            ]);
        }
    }

    /**
     * Sin ADSENSE_CLIENT el sitio no lleva una sola línea de AdSense. Es lo que
     * mantiene limpios el local y los tests, y lo que permite tener los
     * bloques puestos en las vistas antes de que existan las unidades.
     */
    public function test_sin_cliente_configurado_no_hay_nada_de_adsense(): void
    {
        config(['services.adsense.client' => null]);

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        $this->assertStringNotContainsString('adsbygoogle', $html);
        $this->assertStringNotContainsString('googlesyndication', $html);
    }

    /** El cargador va UNA vez. Dos dejan la cola sin procesar y no sirve ninguno. */
    public function test_el_cargador_se_emite_una_sola_vez(): void
    {
        $this->configurarAnuncios();

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'adsbygoogle.js'));
    }

    /** Las tres unidades de la ficha de ciudad, cada una con su push(). */
    public function test_la_ficha_de_ciudad_lleva_las_tres_unidades(): void
    {
        $this->configurarAnuncios();

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        preg_match_all('/data-ad-slot="(\d+)"/', $html, $encontrados);

        $this->assertSame(['1111111111', '2222222222', '3333333333'], $encontrados[1]);
        $this->assertSame(3, substr_count($html, 'adsbygoogle = window.adsbygoogle'));
    }

    /**
     * El push() va inmediatamente después del <ins> y sin envolver. Cualquier
     * requestIdleCallback acá es la regresión que costó dos tercios del RPM.
     */
    public function test_el_push_no_esta_diferido(): void
    {
        $this->configurarAnuncios();

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        $this->assertStringNotContainsString('requestIdleCallback', $html);
        $this->assertMatchesRegularExpression(
            '/<\/ins>\s*<script>\(adsbygoogle = window\.adsbygoogle \|\| \[\]\)\.push\(\{\}\);<\/script>/',
            $html
        );
    }

    /**
     * El de arriba del mapa limita el inventario a formatos horizontales.
     *
     * Estuvo con el patrón de tamaño fijo —sin `data-ad-format`, con el alto
     * puesto desde el CSS— y **no alcanzó**: el tamaño lo manda la unidad del
     * panel de AdSense, y una unidad creada como responsive ignora el
     * `height:50px` del marcado. Pasó en calles-argentinas y se repitió acá,
     * con el 336×280 empujando el buscador fuera de la pantalla del teléfono.
     *
     * `horizontal` con full-width-responsive apagado deja solo 320×50, 320×100
     * y 728×90: 100 px de alto máximo en vez de 280, y sin depender de cómo
     * quedó configurada la unidad, que es algo que el código no puede ver.
     */
    public function test_el_bloque_de_arriba_no_puede_traer_un_formato_alto(): void
    {
        $this->configurarAnuncios();

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        preg_match('/<ins[^>]*data-ad-slot="1111111111"[^>]*>/s', $html, $superior);

        $this->assertNotEmpty($superior, 'No se encontró el bloque de arriba.');
        $this->assertStringContainsString('data-ad-format="horizontal"', $superior[0]);
        $this->assertStringContainsString('data-full-width-responsive="false"', $superior[0]);
        $this->assertStringNotContainsString('data-ad-format="auto"', $superior[0]);
    }

    /**
     * Y reserva 100 px, el mayor de los tres formatos horizontales. Reservar
     * 50 deja saltar la página los 50 de diferencia cuando llega un 320×100 —
     * y el salto ocurre arriba de todo, empujando el buscador.
     */
    public function test_el_bloque_de_arriba_reserva_el_alto_mayor(): void
    {
        $this->configurarAnuncios();

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        preg_match('/<ins[^>]*data-ad-slot="1111111111"[^>]*>/s', $html, $superior);

        $this->assertNotEmpty($superior, 'No se encontró el bloque de arriba.');
        $this->assertStringContainsString('min-height:100px', $superior[0]);
    }

    /** Los de abajo sí son responsive: no tapan ninguna función. */
    public function test_los_bloques_de_abajo_son_responsive(): void
    {
        $this->configurarAnuncios();

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        foreach (['2222222222', '3333333333'] as $slot) {
            preg_match('/<ins[^>]*data-ad-slot="'.$slot.'"[^>]*>/s', $html, $bloque);

            $this->assertStringContainsString('data-ad-format="auto"', $bloque[0]);
            $this->assertStringContainsString('data-full-width-responsive="true"', $bloque[0]);
        }
    }

    /**
     * Un slot sin cargar apaga su bloque y deja los otros andando. Es lo que
     * permite montar las unidades de a una y medir cada posición.
     */
    public function test_un_slot_vacio_apaga_solo_su_bloque(): void
    {
        $this->configurarAnuncios();
        config(['services.adsense.slots.ciudad_medio' => null]);

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        preg_match_all('/data-ad-slot="(\d+)"/', $html, $encontrados);

        $this->assertSame(['1111111111', '3333333333'], $encontrados[1]);
    }

    /**
     * El problema que esta unidad existe para resolver.
     *
     * La ficha de una ciudad grande mide 48.636 px a 390 px de ancho: 58
     * pantallas de teléfono. Las tres unidades originales estaban en la
     * primera y en la última, así que 56 pantallas —donde el visitante pasa
     * todo el tiempo— no llevaban un solo anuncio, y la de abajo de todo, que
     * es la que más rinde, quedaba por debajo de las 58 y en móvil no la veía
     * nadie.
     *
     * Con 26 grupos de letra y una cada 8 son tres unidades intercaladas.
     */
    public function test_el_indice_intercala_unidades_cada_n_grupos(): void
    {
        $this->configurarAnuncios();
        $this->llenarElIndice();

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'data-ad-slot="4444444444"'));
    }

    /**
     * Nunca después del último grupo: ahí ya está la unidad final, y dos
     * seguidas es exactamente la densidad que conviene no tener en un sitio
     * que viene de una penalización algorítmica.
     */
    public function test_el_indice_no_intercala_despues_del_ultimo_grupo(): void
    {
        $this->configurarAnuncios();
        $this->llenarElIndice();
        // 27 grupos con una cada 27 caería justo en el último.
        config(['services.adsense.indice_cada' => 27]);

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        $this->assertStringNotContainsString('data-ad-slot="4444444444"', $html);
    }

    /**
     * Una ciudad chica no lleva ninguna intercalada. El reparto se adapta al
     * largo real de la página en vez de repetir el mismo número de anuncios
     * en una ficha de tres calles y en una de ocho mil.
     */
    public function test_una_ciudad_con_pocas_calles_no_intercala_nada(): void
    {
        $this->configurarAnuncios();

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        $this->assertStringNotContainsString('data-ad-slot="4444444444"', $html);
    }

    /**
     * Las unidades intercaladas reservan su alto.
     *
     * Aparecen mientras el visitante scrollea, que es el peor momento para un
     * salto de layout: sin alto reservado, cada anuncio que carga empuja la
     * lista que el visitante está leyendo. El sitio acaba de subir a 94-95 de
     * rendimiento y el CLS es parte de esa nota.
     */
    public function test_las_unidades_intercaladas_reservan_el_alto(): void
    {
        $this->configurarAnuncios();
        $this->llenarElIndice();

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        preg_match('/<ins[^>]*data-ad-slot="4444444444"[^>]*>/s', $html, $bloque);

        $this->assertNotEmpty($bloque, 'No se encontró la unidad intercalada.');
        $this->assertStringContainsString('min-height:280px', $bloque[0]);
    }

    /**
     * La ficha de país es la página más eficiente del sitio —246 URLs y 2.951
     * clics, 12 por URL contra los 5,2 de la ficha de ciudad— y la migración
     * la dejó sin una sola unidad, aunque el CakePHP sí la monetizaba.
     */
    public function test_la_ficha_de_pais_lleva_sus_dos_unidades(): void
    {
        $this->configurarAnuncios();

        $html = $this->get(self::FICHA_PAIS)->assertOk()->getContent();

        preg_match_all('/data-ad-slot="(\d+)"/', $html, $encontrados);

        $this->assertSame(['5555555555', '6666666666'], $encontrados[1]);
        $this->assertSame(1, substr_count($html, 'adsbygoogle.js'));
    }

    /**
     * El de arriba de la ficha de país lleva la misma restricción: el
     * visitante que llega de `reverse address lookup canada` viene a usar el
     * buscador, y un 336×280 acá lo empuja fuera de la pantalla del teléfono.
     */
    public function test_el_bloque_de_arriba_del_pais_no_puede_traer_un_formato_alto(): void
    {
        $this->configurarAnuncios();

        $html = $this->get(self::FICHA_PAIS)->assertOk()->getContent();

        preg_match('/<ins[^>]*data-ad-slot="5555555555"[^>]*>/s', $html, $bloque);

        $this->assertNotEmpty($bloque, 'No se encontró el bloque de arriba del país.');
        $this->assertStringContainsString('data-ad-format="horizontal"', $bloque[0]);
        $this->assertStringContainsString('data-full-width-responsive="false"', $bloque[0]);
        $this->assertStringContainsString('min-height:100px', $bloque[0]);
    }

    /**
     * Y los bloques que NO son de arriba siguen aprovechando todo el ancho:
     * la restricción es de los dos de arriba, no una regla del sitio. Debajo
     * del contenido un formato más alto rinde más y no tapa ninguna función.
     */
    public function test_los_bloques_de_abajo_conservan_el_ancho_completo(): void
    {
        $this->configurarAnuncios();

        $html = $this->get(self::FICHA)->assertOk()->getContent();

        preg_match('/<ins[^>]*data-ad-slot="3333333333"[^>]*>/s', $html, $final);

        $this->assertNotEmpty($final, 'No se encontró el bloque final.');
        $this->assertStringContainsString('data-full-width-responsive="true"', $final[0]);
    }

    /**
     * Una variable de la vista que se llama como un parámetro del partial no
     * puede tumbar la página.
     *
     * `@include` no aísla el scope, así que el partial ve todo lo que tenga
     * definido la vista que lo incluye. `lugares/pais.blade.php` define
     * `$formato` —el array de líneas de `FormatoDeDireccion`— y el bloque de
     * abajo lo tomó como su `data-ad-format`, que es un string: la ficha de
     * país entera respondió 500 con «htmlspecialchars(): must be of type
     * string, array given», un mensaje que no nombra ni a los anuncios ni al
     * `@include`.
     *
     * Este test recorre el camino real —la ficha de país de Canadá, que tiene
     * ficha de formato— y comprueba que el bloque salga con el formato que le
     * corresponde y no con lo que había dando vueltas en la vista.
     */
    public function test_una_variable_del_padre_no_se_cuela_como_parametro(): void
    {
        $this->configurarAnuncios();

        $html = $this->get(self::FICHA_PAIS)->assertOk()->getContent();

        preg_match('/<ins[^>]*data-ad-slot="6666666666"[^>]*>/s', $html, $bloque);

        $this->assertNotEmpty($bloque, 'No se encontró el bloque del medio del país.');
        $this->assertStringContainsString('data-ad-format="auto"', $bloque[0]);
    }

    /** Sin cliente configurado, tampoco hay nada en la ficha de país. */
    public function test_sin_cliente_la_ficha_de_pais_no_lleva_anuncios(): void
    {
        config(['services.adsense.client' => null]);

        $html = $this->get(self::FICHA_PAIS)->assertOk()->getContent();

        $this->assertStringNotContainsString('adsbygoogle', $html);
    }
}
