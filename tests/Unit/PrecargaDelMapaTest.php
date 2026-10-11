<?php

namespace Tests\Unit;

use App\Support\PrecargaDelMapa;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * El cálculo del tile que se precarga.
 *
 * Es aritmética pura y por eso tiene test propio: si da mal, el navegador
 * baja un tile que después nadie usa —el doble de tráfico y ninguna mejora—
 * y no hay forma de notarlo mirando la página, porque el mapa se ve igual.
 *
 * Los valores esperados salen de la proyección Web Mercator, que es la que
 * usa Leaflet, y están cruzados contra los tiles que Lighthouse observó
 * pidiendo de verdad en la ficha de Marianao: 13/2220/3556 y vecinos.
 */
class PrecargaDelMapaTest extends TestCase
{
    public static function lugares(): array
    {
        return [
            // Marianao, La Habana — el tile que Lighthouse vio como LCP.
            // x = ((-82,43+180)/360)×8192 = 2220,3 → 2220
            // y = ((1 − ln(tan φ + sec φ)/π)/2)×8192 = 3555,6 → 3555
            // Lighthouse vio en pantalla el 3555 y el 3556: los dos entran, el
            // del centro es el 3555.
            'Marianao' => [23.08, -82.43, 13, 'https://tile.openstreetmap.org/13/2220/3555.png'],
            // Calgary, verificado contra la fórmula.
            'Calgary' => [51.05011, -114.08529, 13, 'https://tile.openstreetmap.org/13/1499/2740.png'],
            // El origen: mitad exacta del mundo en los dos ejes.
            'Null Island' => [0.0, 0.0, 1, 'https://tile.openstreetmap.org/1/1/1.png'],
            // Zoom 0: el mundo entero es un solo tile.
            'mundo entero' => [0.0, 0.0, 0, 'https://tile.openstreetmap.org/0/0/0.png'],
        ];
    }

    #[DataProvider('lugares')]
    public function test_calcula_el_tile_del_centro(float $lat, float $lon, int $zoom, string $esperado): void
    {
        $this->assertSame($esperado, PrecargaDelMapa::tileCentral($lat, $lon, $zoom));
    }

    /**
     * Web Mercator no llega a los polos: la fórmula diverge. El dataset tiene
     * ciudades en la Antártida, así que el caso existe de verdad.
     */
    public function test_los_polos_no_tienen_tile(): void
    {
        $this->assertNull(PrecargaDelMapa::tileCentral(89.9, 0, 13));
        $this->assertNull(PrecargaDelMapa::tileCentral(-89.9, 0, 13));
    }

    /** Un zoom fuera de rango se acota en vez de producir una URL inválida. */
    public function test_el_zoom_se_acota(): void
    {
        $url = PrecargaDelMapa::tileCentral(-85.0, 179.999, 99);

        $this->assertNotNull($url);
        $this->assertMatchesRegularExpression(
            '#^https://tile\.openstreetmap\.org/19/(\d+)/(\d+)\.png$#',
            $url
        );

        // Con zoom 19 la grilla es de 2^19 = 524.288 tiles por lado, así que
        // ningún índice puede llegar a ese número.
        preg_match('#/19/(\d+)/(\d+)\.png$#', $url, $partes);
        $this->assertLessThan(524288, (int) $partes[1]);
        $this->assertLessThan(524288, (int) $partes[2]);
    }
}
