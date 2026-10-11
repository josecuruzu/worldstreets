{{--
    Un bloque de AdSense.

    Portado de calles-argentinas-v2, que es del mismo dueño y la misma
    monetización, con las lecciones que allá costaron dinero medido. Lo que
    sigue no es teoría: son dos errores que ya se cometieron.

    ── Por qué el push() va acá y no diferido ─────────────────────────────────

    En el sitio hermano el push() estuvo envuelto en requestIdleCallback. La
    idea era sacarlo del camino crítico, pero requestIdleCallback solo corre
    cuando el navegador queda ocioso, y en una página con LCP de más de cuatro
    segundos eso puede no pasar nunca. Medido en AdSense: los anuncios servidos
    por página cayeron de 3,2 a 1,0 y el RPM de $2,54 a $0,60. Dos tercios de
    los ingresos por una optimización que no ahorraba nada.

    push() solo agrega un objeto a un array. El trabajo pesado —pedir el
    anuncio, armar el iframe— lo hace adsbygoogle.js, que ya se carga async y
    procesa la cola cuando llega, esté llena desde el principio o no. Es además
    lo que documenta Google: el push() va inmediatamente después del <ins>.

    ── Por qué hay un solo cargador ──────────────────────────────────────────

    El otro error fue tener el script del cargador repetido: dos adsbygoogle.js
    en la misma página dejaban `window.adsbygoogle.length` en 4 con la cola sin
    procesar y todos los <ins> en 0×0 — ningún anuncio, ningún error. El
    cargador va UNA vez y en el layout; acá abajo nunca.

    ── Parámetros ────────────────────────────────────────────────────────────

    $slot    (requerido) el data-ad-slot del bloque, del panel de AdSense.
    $clase   (opcional)  clases del contenedor.
    $estilo  (opcional)  estilo del <ins>. Por defecto display:block.
    $formato (opcional)  data-ad-format. Por defecto "auto". Con $fijo no se
                         emite: son incompatibles.
    $fijo    (opcional)  true para tamaño fijo por CSS: sin data-ad-format y
                         sin full-width-responsive. ⚠️ Leer abajo antes de
                         usarlo: no alcanza por sí solo.
    $anchoCompleto (opc) false para data-full-width-responsive="false".

    ── Por qué "fijo" NO alcanza, que costó una ronda en los dos proyectos ───

    El patrón de tamaño fijo —`display:inline-block;width:320px;height:50px` y
    sin `data-ad-format`— es el que documenta Google, y aun así AdSense sirve
    un 336×280 y empuja el buscador fuera de la pantalla del teléfono.

    La razón es que **el tamaño lo manda la unidad del panel, no el marcado**.
    Si la unidad se creó como *Responsive* —que es lo que ofrece AdSense por
    defecto— adsbygoogle.js reescribe el alto del <ins> con el formato que
    eligió, y el `height:50px` del estilo no lo frena. El marcado fijo solo
    manda cuando la unidad del panel también es de tamaño fijo.

    Pasó en calles-argentinas y se repitió acá. Es fácil de mirar: las
    unidades que venían del CakePHP se llaman "worldstreets **adaptable**
    top" y "adaptable bottom" — o sea, responsive, y el nombre lo dice.

    ── La salida que no depende del panel ────────────────────────────────────

    `data-ad-format="horizontal"` con `data-full-width-responsive="false"`.
    Limita el inventario a los formatos horizontales —320×50, 320×100,
    728×90—, así que el alto máximo pasa de 280 px a 100. Funciona con la
    unidad configurada de cualquiera de las dos formas, que es la propiedad
    que hacía falta: el código no puede verificar cómo quedó el panel.

    Por eso los dos bloques de arriba —ficha de ciudad y ficha de país— usan
    "horizontal" y NO `fijo`, y reservan 100 px y no 50: entre un 320×50 y un
    320×100 hay 50 px de diferencia, y reservar el mayor es lo que evita que
    la página salte cuando llega el anuncio.

    `fijo` queda para el día que las unidades del panel sean de tamaño fijo de
    verdad; entonces es más determinista que "horizontal".

    No lleva aria-hidden. Lo tenía, y un aria-hidden sobre un bloque que
    contiene un iframe con enlaces deja a un lector de pantalla navegando por
    dentro de algo declarado invisible, que es peor que no ponerlo.

    ── Sin configurar no existe ──────────────────────────────────────────────

    Sin ADSENSE_CLIENT, o sin el slot cargado, el partial no emite nada. Es lo
    que permite tenerlo puesto en las vistas antes de que existan las unidades
    en el panel, y lo que mantiene los tests y el local limpios de anuncios.
--}}
{{--
    ── Por qué los parámetros se normalizan ──────────────────────────────────

    `@include` NO aísla el scope: el partial ve todas las variables de la
    vista que lo incluye. Así que cualquier vista que tenga una variable
    llamada como un parámetro de acá se la pasa sin quererlo.

    Ya pasó. `lugares/pais.blade.php` define `$formato` —el array de
    `FormatoDeDireccion`, las líneas de una dirección de ese país— y el bloque
    de abajo lo tomó como su `data-ad-format`, que espera un string. La página
    entera se cayó con «htmlspecialchars(): must be of type string, array
    given», que no menciona ni a los anuncios ni al `@include`.

    Por eso cada parámetro se valida por tipo antes de usarse, y no con un
    `??` que solo mira si existe. Lo fija `test_una_variable_del_padre_...`.
--}}
@php
    $cliente = config('services.adsense.client');

    $anuncioSlot = is_scalar($slot ?? null) ? (string) $slot : '';
    $anuncioClase = is_string($clase ?? null) ? $clase : '';
    $anuncioEstilo = is_string($estilo ?? null) ? $estilo : 'display:block';
    $anuncioFormato = is_string($formato ?? null) ? $formato : 'auto';
    $anuncioFijo = ($fijo ?? false) === true;
    $anuncioAncho = ($anchoCompleto ?? true) === true ? 'true' : 'false';
@endphp

@if ($cliente && $anuncioSlot !== '')
    <div class="{{ $anuncioClase }}">
        <ins class="adsbygoogle"
             style="{{ $anuncioEstilo }}"
             data-ad-client="{{ $cliente }}"
             data-ad-slot="{{ $anuncioSlot }}"
             @if (! $anuncioFijo)
                 data-ad-format="{{ $anuncioFormato }}"
                 data-full-width-responsive="{{ $anuncioAncho }}"
             @endif
        ></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
    </div>
@endif
