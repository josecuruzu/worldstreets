<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
{{-- Los <loc> se reescriben contra el host canónico: `route()` usa APP_URL,
     que durante el corte apunta al dominio de pruebas, y estas listas están
     cacheadas 12 horas. Declarar el corpus entero desde el dominio equivocado
     es exactamente la forma de que Google indexe la copia.

     Sin <lastmod>, <changefreq> ni <priority>: Google ignora los dos últimos
     desde hace años, y un lastmod que no refleja un cambio real de contenido
     es peor que no ponerlo. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url><loc>{{ \App\Support\Canonico::url($url) }}</loc></url>
@endforeach
</urlset>
