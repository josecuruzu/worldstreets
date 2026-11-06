<?php

/*
|--------------------------------------------------------------------------
| Las fichas de lugar, en inglés
|--------------------------------------------------------------------------
|
| El inglés es el idioma del sitio, así que estas cadenas son las que estaban
| escritas dentro de las vistas. Salieron de ahí cuando apareció la versión en
| español: un solo juego de plantillas y dos juegos de texto.
|
| Los títulos y las descripciones NO se tocaron al mudarlos. La frase
| `Reverse Address Lookup Free in {lugar}` es el 73% de los clics del sitio y
| ya la rompió un rediseño una vez; `MetadatosDeBusquedaTest` existe por eso.
|
*/

return [

    'inicio' => 'Worldwide',
    'region_desconocida' => 'Unknown region',
    'esta_region' => 'this region',
    'buscar' => 'Search',
    'buscando' => 'Searching…',
    'buscar_una_ciudad' => 'Search a city',

    // Plurales para trans_choice().
    'calles' => 'street|streets',
    'ciudades' => 'city|cities',
    'regiones' => 'region|regions',
    'calles_may' => 'Street|Streets',
    'ciudades_may' => 'City|Cities',
    'regiones_may' => 'Region|Regions',

    'pais' => [
        'titulo' => 'Reverse Address Lookup Free in :pais',
        'descripcion_con_calles' => 'Do unlimited reverse address searches in cities of :pais for free. :calles :palabra_calles on record across :ciudades :palabra_ciudades — find the physical address of a place on the map.',
        'descripcion_sin_calles' => 'Do unlimited reverse address searches in cities of :pais for free. Browse :regiones :palabra_regiones and find the physical address of a place on the map.',
        'h1' => 'Reverse address lookup in :pais',
        'intro' => 'Search a city below and you get its map, its street list A to Z, and a box to look up a street with a house number.',
        'buscador_label' => 'City in :pais',
        'buscador_placeholder' => 'Find a city in :pais…',
        'sin_calles_todavia' => 'no streets yet',
        'sin_resultados' => 'No city in :pais matched that name.',
        'cifra_ciudades' => ':palabra with a street list',
        'cifra_calles' => ':palabra on record',
        'regiones_titulo' => 'Regions',
        'regiones_sub' => 'Streets on record in each region.',
        'mejores_titulo' => 'Best covered cities',
        'mejores_sub' => 'Where we hold the most streets in :pais.',
        'cobertura_titulo' => 'Deepest coverage here',
        'cobertura_calles' => ':calles :palabra on record.',
        'cobertura_abrir' => 'Open :ciudad',
        'vacio_titulo' => 'No streets yet',
        'vacio_texto' => 'No city in :pais has a street list yet. The lists are written by the people who look streets up.',
    ],

    'direccion' => [
        'titulo' => 'How an address works in :pais',
        'intro' => 'What a complete :pais address looks like, and what a sender from abroad usually gets wrong.',
        'lineas' => 'The lines, top to bottom',
        'trampas' => 'What trips people up',
        'aviso' => 'The lookup above finds a street and house number on the map. It does not tell you who lives at an address — no version of this site ever has.',
    ],

    'provincia' => [
        'titulo' => 'Reverse Address Lookup Free in :provincia, :pais',
        'descripcion_con_ciudades' => 'Do unlimited reverse address searches in cities of :provincia, :pais. Every city with a street list has its own page, with the map and its list of streets.',
        'descripcion_sin_ciudades' => 'Do unlimited reverse address searches in cities of :provincia, :pais. Find the physical address of a place on the map.',
        'sub' => 'cities with a street list, largest first',
        'sub_sin_region' => 'Cities in :pais that GeoNames does not assign to any region',
        'vacio' => 'No city in :provincia has a street list yet. The lists are written by the people who look streets up — search a city and you start one.',
    ],

    'ciudad' => [
        'titulo' => 'Reverse Address Lookup Free in :ciudad, :region',
        'descripcion' => 'Do unlimited reverse address searches in :lugar. :calles :palabra on record, A to Z — find the physical address of a place on the map.',
        // Con población real: cada ficha dice algo propio y no solo el nombre.
        'descripcion_con_poblacion' => 'Do unlimited reverse address searches in :lugar, home to :poblacion people. :calles :palabra on record, A to Z — find the physical address of a place on the map.',
        'altura_label' => 'House number',
        'altura_placeholder' => 'No.',
        'calle_label' => 'Find a street in :ciudad',
        'calle_placeholder' => 'Find a street in :ciudad',
        'localizando' => 'Locating…',
        'ver_en_osm' => 'View on OpenStreetMap',
        'no_encontrada' => 'We could not place that house number in :ciudad. Check the street name and the number, or search the street without a number.',
        'coincidentes' => 'Matching streets',
        'sin_coincidencias_1' => 'No street matched “',
        'sin_coincidencias_2' => '” in :ciudad. The list below is every street on record here.',
        'indice_titulo' => 'Streets in :ciudad',
        'indice_sub' => 'Every street on record for this city, A to Z.',
        'saltar_tipo' => 'Jump to a street type',
        'saltar_letra' => 'Jump to a letter',
        'de_total' => ':mostradas of :total',
        'recortadas' => 'Long letters show the first :tope. Use the search above to find any other street in :ciudad.',
        'calles_registradas' => ':calles streets on record',
        'sobre_el_lugar' => 'About this place',
        'campo_pais' => 'Country',
        'campo_region' => 'Region',
        'campo_poblacion' => 'Population',
        'campo_coordenadas' => 'Coordinates',
        'personas' => ':n people',
        'tambien_conocida' => 'Also known as',
        'mas_en' => 'More in :region',
        'todas_las_ciudades' => 'All cities in :region',
        'compartir' => 'Share',
        'compartido' => 'Link copied',
        'copiar' => 'Copy address',
        'copiado' => 'Copied',
        'no_se_pudo_copiar' => 'Copy it by hand: ',
        'mapa_de' => 'Map of :ciudad',
    ],

];
