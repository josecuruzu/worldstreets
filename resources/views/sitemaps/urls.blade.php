<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
{{-- Sin <lastmod>, <changefreq> ni <priority>: Google ignora los dos últimos
     desde hace años, y un lastmod que no refleja un cambio real de contenido
     es peor que no ponerlo. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url><loc>{{ $url }}</loc></url>
@endforeach
</urlset>
