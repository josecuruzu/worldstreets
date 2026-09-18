<?php

namespace Tests\Feature;

use App\Http\Middleware\BloqueaScrapers;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El bloqueo de bots que consumen el sitio sin dejar nada.
 *
 * Medido en AdSense, 12 meses. Las impresiones de anuncio por página vista
 * separan a una persona de un scraper sin lugar a dudas:
 *
 *     Estados Unidos   5.213 páginas vistas   4,45 imp/pv   $141,10
 *     China           28.720                  0,02          $  0,38
 *     Singapur         9.431                  0,04          $  0,35
 *
 * Son 38.151 páginas vistas —el 59% del total— por 73 centavos al año, sobre
 * un hosting compartido que responde en 2.400 ms. Cada una es tiempo que no
 * está para Googlebot, que hace 240 peticiones diarias contra 18,28 M de URLs.
 *
 * El riesgo de esto es pasarse: un patrón amplio como `/bot/i` se lleva puesto
 * a Googlebot, y el sitio deja de existir sin que falle nada. Por eso la mitad
 * de estos tests verifica lo que NO se bloquea.
 */
class BloqueoDeScrapersTest extends TestCase
{
    /** @return list<array{0: string, 1: string}> */
    public static function scrapers(): array
    {
        return [
            ['Bytespider', 'Mozilla/5.0 (compatible; Bytespider; https://zhanzhang.toutiao.com/)'],
            ['PetalBot', 'Mozilla/5.0 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)'],
            ['AhrefsBot', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)'],
            ['SemrushBot', 'Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)'],
            ['MJ12bot', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)'],
            ['DotBot', 'Mozilla/5.0 (compatible; DotBot/1.2; +https://opensiteexplorer.org/dotbot)'],
            ['Baiduspider', 'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)'],
            ['YisouSpider', 'Mozilla/5.0 (compatible; YisouSpider/5.0; http://www.yisou.com/help/help_yisou.html)'],
        ];
    }

    #[DataProvider('scrapers')]
    public function test_un_scraper_recibe_403(string $nombre, string $agente): void
    {
        $this->withHeaders(['User-Agent' => $agente])
            ->get('/')
            ->assertStatus(403);
    }

    /** @return list<array{0: string, 1: string}> */
    public static function agentesLegitimos(): array
    {
        return [
            ['Googlebot', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            ['Googlebot móvil', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/W.X.Y.Z Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            ['Google AdSense', 'Mediapartners-Google'],
            ['Bingbot', 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'],
            ['DuckDuckBot', 'DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)'],
            ['Applebot', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)'],
            ['WhatsApp', 'WhatsApp/2.23.20.0'],
            ['un iPhone', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'],
            ['Chrome en Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'],
        ];
    }

    /**
     * Lo que más importa de todo esto. Bloquear a Googlebot sería sacar al
     * sitio de Google sin que nada falle: las páginas siguen respondiendo 200
     * para cualquiera que las mire a mano.
     */
    #[DataProvider('agentesLegitimos')]
    public function test_un_agente_legitimo_pasa(string $nombre, string $agente): void
    {
        $this->assertFalse(
            BloqueaScrapers::esScraper($agente),
            "{$nombre} quedó bloqueado y no debería"
        );
    }

    /**
     * Googlebot trae "bot" en el nombre, igual que Bytespider trae "spider":
     * la coincidencia tiene que ser contra nombres exactos, nunca contra un
     * patrón que describa a la familia.
     */
    public function test_no_bloquea_por_contener_la_palabra_bot(): void
    {
        foreach (['bot', 'spider', 'crawler', 'robot'] as $palabra) {
            $this->assertFalse(
                BloqueaScrapers::esScraper("Mozilla/5.0 (compatible; MiNavegador/1.0; contiene {$palabra})"),
                "un agente con la palabra \"{$palabra}\" no tiene por qué estar bloqueado"
            );
        }
    }

    /** Una petición sin User-Agent no es necesariamente un bot. */
    public function test_sin_user_agent_no_se_bloquea(): void
    {
        $this->assertFalse(BloqueaScrapers::esScraper(''));
    }

    public function test_la_comparacion_no_distingue_mayusculas(): void
    {
        $this->assertTrue(BloqueaScrapers::esScraper('BYTESPIDER'));
        $this->assertTrue(BloqueaScrapers::esScraper('AhrefsBot'));
    }

    /**
     * El 403 corta antes que nada: un scraper no tiene que llegar a abrir una
     * sesión ni a consultar la base.
     */
    public function test_el_bloqueo_corta_antes_de_tocar_la_base(): void
    {
        $respuesta = $this->withHeaders(['User-Agent' => 'Bytespider'])->get('/canada/ontario');

        $respuesta->assertStatus(403);
        $this->assertSame([], $respuesta->headers->getCookies());
    }

    /** Los que respetan robots.txt se van antes de pedir una página. */
    public function test_el_robots_declara_los_mismos_bots(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        foreach (['Bytespider', 'PetalBot', 'AhrefsBot', 'SemrushBot', 'MJ12bot'] as $bot) {
            $this->assertStringContainsString("User-agent: {$bot}", $robots);
        }

        $this->assertStringNotContainsString('User-agent: Googlebot', $robots);
    }
}
