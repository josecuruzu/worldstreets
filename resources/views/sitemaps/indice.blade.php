<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
{{-- El índice: un archivo de países y provincias, y uno de ciudades por cada
     país que tenga alguna con página. --}}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($mapas as $mapa)
    <sitemap><loc>{{ $mapa }}</loc></sitemap>
@endforeach
</sitemapindex>
