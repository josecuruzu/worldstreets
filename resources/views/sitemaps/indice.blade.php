<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
{{-- El índice: un archivo de países y provincias, y uno de ciudades por cada
     país que tenga alguna con página.

     Los <loc> se reescriben contra el host canónico, y no salen con el de
     `route()`. Son dos cosas distintas: `route()` usa APP_URL, que durante el
     corte apunta al dominio de pruebas, y además estas listas están cacheadas
     12 horas, así que el host quedaría congelado adentro del caché. --}}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($mapas as $mapa)
    <sitemap><loc>{{ \App\Support\Canonico::url($mapa) }}</loc></sitemap>
@endforeach
</sitemapindex>
