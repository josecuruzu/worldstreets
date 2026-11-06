<?php

/*
|--------------------------------------------------------------------------
| Las fichas de lugar, en español
|--------------------------------------------------------------------------
|
| ── Español neutro, y no es un detalle de estilo ──────────────────────────
|
| El dueño del proyecto es argentino y el sitio hermano está escrito en
| rioplatense. Acá no: el público medido es cubano, colombiano, venezolano,
| español y peruano. Cuba es el segundo país del sitio por clics (832) y
| España el cuarto (422); Argentina no aparece entre los diez primeros.
|
| Entonces: tuteo, nada de voseo ("busca", no "buscá"), nada de "vosotros", y
| vocabulario que no sea de un solo país. "Calle y número", que se entiende en
| todas partes, y no "altura", que fuera del Río de la Plata no significa nada.
|
| ── Los títulos salen de las consultas, no de traducir el inglés ──────────
|
| El inglés titula `Reverse Address Lookup Free in {lugar}` porque esa frase
| es el 73% de sus clics. En español la frase equivalente no existe: lo que la
| gente escribe, medido en Search Console, es otra cosa.
|
|     calles del cerro la habana        17 clics   pos  9,8
|     direcciones de soacha             32 clics   pos  6,3
|     calles de matanzas cuba            8 clics   pos 12,7
|     calles de marianao                 5 clics   pos  9,7
|     buscar dirección en cuba           2 clics   pos  7,7
|     buscador de direcciones chile      2 clics   pos  9,3
|
| Dos familias, `calles de {lugar}` y `direcciones de {lugar}`, y ninguna de
| las dos es la traducción de "reverse address lookup". Por eso el título en
| español es `Calles y direcciones de {lugar}`: entra en los 60 caracteres que
| muestra Google con el sufijo de marca, y cubre las dos.
|
*/

return [

    'inicio' => 'Todo el mundo',
    'region_desconocida' => 'Región sin asignar',
    'esta_region' => 'esta región',
    'buscar' => 'Buscar',
    'buscando' => 'Buscando…',
    'buscar_una_ciudad' => 'Buscar una ciudad',

    // Plurales para trans_choice().
    'calles' => 'calle|calles',
    'ciudades' => 'ciudad|ciudades',
    'regiones' => 'región|regiones',
    'calles_may' => 'Calle|Calles',
    'ciudades_may' => 'Ciudad|Ciudades',
    'regiones_may' => 'Región|Regiones',

    'pais' => [
        'titulo' => 'Calles y direcciones de :pais',
        'descripcion_con_calles' => 'Busca una dirección por calle y número en cualquier ciudad de :pais, gratis y sin registrarte. :calles :palabra_calles registradas en :ciudades :palabra_ciudades, con el mapa de cada una.',
        'descripcion_sin_calles' => 'Busca una dirección por calle y número en cualquier ciudad de :pais, gratis y sin registrarte. Recorre :regiones :palabra_regiones y encuentra el lugar en el mapa.',
        'h1' => 'Buscar direcciones y calles en :pais',
        'intro' => 'Elige una ciudad y vas a ver su mapa, su lista de calles de la A a la Z, y un campo para ubicar una calle con su número.',
        'buscador_label' => 'Ciudad de :pais',
        'buscador_placeholder' => 'Busca una ciudad de :pais…',
        'sin_calles_todavia' => 'todavía sin calles',
        'sin_resultados' => 'Ninguna ciudad de :pais coincide con ese nombre.',
        'cifra_ciudades' => ':palabra con lista de calles',
        'cifra_calles' => ':palabra registradas',
        'regiones_titulo' => 'Regiones',
        'regiones_sub' => 'Calles registradas en cada región.',
        'mejores_titulo' => 'Ciudades mejor cubiertas',
        'mejores_sub' => 'Donde tenemos más calles de :pais.',
        'cobertura_titulo' => 'La mejor cobertura',
        'cobertura_calles' => ':calles :palabra registradas.',
        'cobertura_abrir' => 'Abrir :ciudad',
        'vacio_titulo' => 'Todavía sin calles',
        'vacio_texto' => 'Ninguna ciudad de :pais tiene lista de calles todavía. Las listas las escribe la gente que busca direcciones acá.',
    ],

    'direccion' => [
        'titulo' => 'Cómo se escribe una dirección en :pais',
        'intro' => 'Cómo es una dirección completa de :pais, y qué suele equivocar quien escribe desde afuera.',
        'lineas' => 'Las líneas, de arriba abajo',
        'trampas' => 'Dónde se equivoca la gente',
        'aviso' => 'El buscador de arriba ubica una calle y un número en el mapa. No dice quién vive en una dirección — ninguna versión de este sitio lo hizo nunca.',
    ],

    'provincia' => [
        'titulo' => 'Calles y direcciones de :provincia, :pais',
        'descripcion_con_ciudades' => 'Busca direcciones por calle y número en las ciudades de :provincia, :pais. Cada ciudad con lista de calles tiene su página, con el mapa y su lista de calles.',
        'descripcion_sin_ciudades' => 'Busca direcciones por calle y número en las ciudades de :provincia, :pais. Encuentra el lugar en el mapa.',
        'sub' => 'ciudades con lista de calles, de mayor a menor',
        'sub_sin_region' => 'Ciudades de :pais que GeoNames no asigna a ninguna región',
        'vacio' => 'Ninguna ciudad de :provincia tiene lista de calles todavía. Las listas las escribe la gente que busca direcciones — busca una ciudad y empiezas la suya.',
    ],

    'ciudad' => [
        'titulo' => 'Calles y direcciones de :ciudad, :region',
        'descripcion' => 'Busca una dirección por calle y número en :lugar. :calles :palabra registradas de la A a la Z, con el mapa y la ubicación exacta.',
        'descripcion_con_poblacion' => 'Busca una dirección por calle y número en :lugar, con :poblacion habitantes. :calles :palabra registradas de la A a la Z, con el mapa y la ubicación exacta.',
        'altura_label' => 'Número',
        'altura_placeholder' => 'N.º',
        'calle_label' => 'Busca una calle de :ciudad',
        'calle_placeholder' => 'Busca una calle de :ciudad',
        'localizando' => 'Ubicando…',
        'ver_en_osm' => 'Ver en OpenStreetMap',
        'no_encontrada' => 'No pudimos ubicar ese número en :ciudad. Revisa el nombre de la calle y el número, o busca la calle sin número.',
        'coincidentes' => 'Calles que coinciden',
        'sin_coincidencias_1' => 'Ninguna calle coincide con “',
        'sin_coincidencias_2' => '” en :ciudad. La lista de abajo es todo lo que tenemos registrado acá.',
        'indice_titulo' => 'Calles de :ciudad',
        'indice_sub' => 'Todas las calles registradas en esta ciudad, de la A a la Z.',
        'saltar_tipo' => 'Ir a un tipo de vía',
        'saltar_letra' => 'Ir a una letra',
        'de_total' => ':mostradas de :total',
        'recortadas' => 'Las letras largas muestran las primeras :tope. Usa el buscador de arriba para encontrar cualquier otra calle de :ciudad.',
        'calles_registradas' => ':calles calles registradas',
        'sobre_el_lugar' => 'Sobre este lugar',
        'campo_pais' => 'País',
        'campo_region' => 'Región',
        'campo_poblacion' => 'Población',
        'campo_coordenadas' => 'Coordenadas',
        'personas' => ':n habitantes',
        'tambien_conocida' => 'También se la conoce como',
        'mas_en' => 'Más en :region',
        'todas_las_ciudades' => 'Todas las ciudades de :region',
        'compartir' => 'Compartir',
        'compartido' => 'Enlace copiado',
        'copiar' => 'Copiar dirección',
        'copiado' => 'Copiado',
        'no_se_pudo_copiar' => 'Cópiala a mano: ',
        'mapa_de' => 'Mapa de :ciudad',
    ],

];
