<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * El tipo de vía que abre el nombre de una calle en español.
 *
 * ── El problema ───────────────────────────────────────────────────────────
 *
 * En español el tipo de vía va ADELANTE —«Calle 13», «Carrera 7»— y la gente
 * lo escribe abreviado. Como la lista de calles del sitio la escriben los
 * propios visitantes, la misma calle entra varias veces con grafías
 * distintas. Medido sobre el corpus real:
 *
 *     calle   1.599   (18 países)        cl    1.311   (Colombia)
 *     carrera   137   (5 países)         cr    1.077   (Colombia)
 *     avenida   295   (18 países)        av      328 / ave 83
 *     pasaje     47                      psje    424   (Uruguay)
 *
 * En Colombia eso son **710 grupos de calles guardados dos veces**: `Calle 13`
 * y `Cl 13` en Soacha son la misma calle en dos filas, con el contador
 * partido y las dos ocupando lugar en la lista.
 *
 * Y hace algo peor que duplicar: **rompe el índice A–Z**. El 81% de las calles
 * colombianas empieza con «C» —de Calle y de Carrera—, y el índice muestra 120
 * por letra, así que publica el 4% del corpus colombiano. Soacha es la
 * consulta en español con más clics del sitio.
 *
 * ── Lo que esta clase NO hace ─────────────────────────────────────────────
 *
 * Solo toca los países hispanohablantes. En inglés el tipo va al FINAL
 * —«Macleod Trail SE»— así que ni el duplicado ni el problema del índice
 * existen, y Canadá son 471.503 calles que no hay por qué tocar.
 *
 * Tampoco toca `carrer`, que es el catalán de «calle»: es otro idioma, no una
 * abreviatura. Fusionarlo con «calle» borraría el nombre real de la vía.
 *
 * Y no toca títulos. `dr` (211 calles), `gral` (48) y `sr` (61) parecen
 * abreviaturas y no lo son: son «Dr. Carlos Salvagno», «Gral. Artigas».
 * Están verificados uno por uno contra ejemplos de la base.
 */
final class TipoDeVia
{
    /**
     * Las abreviaturas verificadas, cada una con su forma canónica.
     *
     * Salieron de contar las primeras palabras del corpus, no de la memoria,
     * y cada una se comprobó con filas reales:
     *
     *     cl 34f (CO)            psje bolivar (UY)      vda la loma (CO)
     *     cr 32d (CO)            cno las pleyades (UY)  sda 128 (UY)
     *     tv 49 sur (CO)         dg 60d (CO)            ave 74 (CU)
     */
    private const ABREVIATURAS = [
        'cl' => 'calle',
        'cll' => 'calle',
        'c' => 'calle',
        'cr' => 'carrera',
        'cra' => 'carrera',
        'kra' => 'carrera',
        'kr' => 'carrera',
        'av' => 'avenida',
        'ave' => 'avenida',
        'avda' => 'avenida',
        'avd' => 'avenida',
        'psje' => 'pasaje',
        'pje' => 'pasaje',
        'cno' => 'camino',
        'tv' => 'transversal',
        'trans' => 'transversal',
        'dg' => 'diagonal',
        'diag' => 'diagonal',
        'vda' => 'vereda',
        'sda' => 'senda',
        'ctra' => 'carretera',
        'crta' => 'carretera',
        'autop' => 'autopista',
        'bul' => 'bulevar',
        'blvd' => 'bulevar',
    ];

    /**
     * Los tipos canónicos, en el orden en que se muestran.
     *
     * No es alfabético: es por cuánto pesan. «Calle» y «Carrera» son el 70%
     * del corpus colombiano y tienen que estar arriba.
     */
    public const CANONICOS = [
        'calle',
        'carrera',
        'avenida',
        'diagonal',
        'transversal',
        'carretera',
        'autopista',
        'bulevar',
        'camino',
        'pasaje',
        'paseo',
        'senda',
        'vereda',
        'plaza',
        'ronda',
        'travesia',
    ];

    /**
     * Expande la abreviatura que abre un nombre de calle.
     *
     * `cl 13` → `calle 13`. Devuelve el nombre tal cual si no empieza con una
     * abreviatura conocida, o si el país no es hispanohablante.
     *
     * El resto del nombre no se toca: `cl 34f` → `calle 34f`, con la efe y
     * todo. Lo único que se normaliza es la palabra del tipo.
     */
    public static function normalizar(string $nombre, ?string $paisId): string
    {
        if (! Idioma::cubre($paisId)) {
            return $nombre;
        }

        $nombre = trim($nombre);

        /*
         * El separador puede ser espacio o punto —`C.Pertera` existe en la
         * base—, y hace falta que haya algo después: `cl` a secas no es una
         * calle, es un nombre de una palabra que no sabemos qué es.
         */
        if (! preg_match('/^(\pL+)[.\s]+(\S.*)$/u', $nombre, $partes)) {
            return $nombre;
        }

        $abreviatura = Str::lower(Str::ascii($partes[1]));

        if (! isset(self::ABREVIATURAS[$abreviatura])) {
            return $nombre;
        }

        return self::ABREVIATURAS[$abreviatura].' '.$partes[2];
    }

    /**
     * El tipo de vía con el que empieza el nombre, ya canónico, o null.
     *
     * Es lo que usa el índice de la ficha de ciudad para agrupar. Acepta tanto
     * la forma larga como la abreviada, así que funciona igual antes y después
     * de normalizar los datos.
     */
    public static function de(string $nombre): ?string
    {
        if (! preg_match('/^(\pL+)[.\s]+\S/u', trim($nombre), $partes)) {
            return null;
        }

        $primera = Str::lower(Str::ascii($partes[1]));
        $canonico = self::ABREVIATURAS[$primera] ?? $primera;

        return in_array($canonico, self::CANONICOS, true) ? $canonico : null;
    }

    /**
     * Lo que queda del nombre después del tipo de vía.
     *
     * `calle 13 sur` → `13 sur`. Es lo que se muestra dentro del grupo: repetir
     * «Calle» en cada una de las 1.297 filas de un grupo llamado «Calle» es
     * ruido, y en un teléfono es ruido que ocupa media pantalla.
     */
    public static function resto(string $nombre): string
    {
        if (self::de($nombre) === null) {
            return trim($nombre);
        }

        preg_match('/^\pL+[.\s]+(\S.*)$/u', trim($nombre), $partes);

        return $partes[1] ?? trim($nombre);
    }
}
