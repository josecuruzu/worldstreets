<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta los bots que consumen el sitio sin dejar nada.
 *
 * El dato que lo justifica sale del reporte de AdSense de los últimos 12
 * meses. Las impresiones de anuncio por página vista separan a una persona de
 * un scraper sin lugar a dudas:
 *
 *     Estados Unidos      5.213 páginas vistas   4,45 imp/pv    $141,10
 *     Reino Unido         1.131                  5,40           $ 9,17
 *     China              28.720                  0,02           $ 0,38
 *     Singapur            9.431                  0,04           $ 0,35
 *
 * Son 38.151 páginas vistas —el 59% del total— que dejaron 73 centavos en un
 * año, sobre un hosting compartido que responde en 2.400 ms. Cada una de esas
 * peticiones es tiempo que no está disponible para Googlebot, que hoy hace 240
 * al día contra 18,28 M de URLs conocidas.
 *
 * ── Criterio de la lista ──────────────────────────────────────────────────
 *
 * Solo nombres exactos y solo de bots que no traen a nadie a este sitio: el
 * producto está en inglés y el 83,1% de los ingresos viene del mercado anglo.
 * Nada de patrones amplios — un `/bot/i` se lleva puesto a Googlebot y ahí el
 * remedio es peor que la enfermedad.
 *
 * NO están en la lista, a propósito: Googlebot, Bingbot, DuckDuckBot,
 * Applebot, y los rastreadores de las redes que arman la vista previa de un
 * link compartido.
 */
class BloqueaScrapers
{
    /**
     * Los agentes que se rechazan, en minúsculas.
     *
     * @var list<string>
     */
    private const AGENTES = [
        // Rastreo masivo desde Asia. Bytespider es el más agresivo de todos y
        // no respeta robots.txt; Petal es el de Huawei.
        'bytespider',
        'petalbot',
        'yisouspider',
        'sogou',
        'baiduspider',

        // Herramientas de SEO. Rastrean el sitio entero para vender informes
        // sobre él; no mandan una sola visita.
        'ahrefsbot',
        'semrushbot',
        'dotbot',
        'mj12bot',
        'blexbot',
        'dataforseobot',
        'serpstatbot',
        'zoominfobot',
        'seokicks',
        'megaindex',
    ];

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        if ($this->esScraper((string) $peticion->userAgent())) {
            /*
             * 403 y no 429: no es que se pasó de rápido, es que no se lo quiere
             * acá. Sin cuerpo, para no gastar en generarlo.
             */
            abort(403);
        }

        return $siguiente($peticion);
    }

    public static function esScraper(string $agente): bool
    {
        if ($agente === '') {
            return false;
        }

        $agente = mb_strtolower($agente);

        foreach (self::AGENTES as $bloqueado) {
            if (str_contains($agente, $bloqueado)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los mismos nombres, para el robots.txt.
     *
     * Los que lo respetan se van solos y no llegan a pedir una página; para
     * los que no —Bytespider es el caso típico— está el 403.
     *
     * @return list<string>
     */
    public static function agentes(): array
    {
        return self::AGENTES;
    }
}
