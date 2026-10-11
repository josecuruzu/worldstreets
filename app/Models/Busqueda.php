<?php

namespace App\Models;

use App\Support\Idioma;
use App\Support\TipoDeVia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Busqueda extends Model
{
    use HasFactory;

    protected $table = 'busquedas';

    protected $fillable = [
        'ciudad_id',
        'nombre_calle',
        'nombre_calle_compacto',
        'cantidad_busquedas',
    ];

    protected $casts = [
        'ciudad_id' => 'integer',
        'cantidad_busquedas' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_id');
    }

    /**
     * Cuántas calles se listan por letra.
     *
     * El tope es por letra y no global a propósito. Con un tope global, una
     * ciudad grande lo agota en las primeras iniciales: Cape Town llenaba las
     * 2.000 entre `#`, A y B, y de la C a la Z la barra salía apagada como si
     * no hubiera ninguna calle. Es peor que truncar — la página afirma algo
     * falso sobre su propio contenido.
     */
    public const CALLES_POR_LETRA = 120;

    /*
     * ── Por qué el índice agrupa por tipo de vía en español ───────────────
     *
     * Agrupar por la letra inicial funciona en inglés, donde el tipo de vía va
     * al final —`Macleod Trail SE`— y la primera letra es la del nombre real.
     * En español va adelante, y eso apila todo bajo la misma letra. Medido:
     *
     *     Colombia   81% de las calles bajo la «C»   (Calle, Carrera)
     *     España     42%                             (Calle)
     *     Cuba       29%    México 26%    Venezuela 29%
     *
     * Con el tope de 120 por grupo, la ficha colombiana publicaba el 4% de sus
     * calles. Y Soacha es la consulta en español con más clics del sitio.
     *
     * Agrupando por tipo, una ficha colombiana abre con «Calle» y «Carrera»,
     * que es por donde el visitante entra, y adentro de cada grupo las calles
     * van ordenadas como números: 1, 2, 3, 10, 10A, 11 — no 1, 10, 10A, 11, 2,
     * que es lo que da el orden alfabético sobre una cuadrícula numerada.
     */

    /**
     * Tope de seguridad al leer. La ciudad con más calles tiene 36.590, así que
     * esto no recorta a nadie hoy; está para que un import futuro no traiga
     * medio millón de filas a memoria.
     */
    private const TOPE_DE_LECTURA = 60000;

    /**
     * Las calles de una ciudad agrupadas por su inicial, de la A a la Z.
     *
     * Es la jugada de contenido del proyecto: convierte una ficha delgada en
     * una página sustancial sin crear una sola URL nueva. Por eso va entera en
     * el HTML y la navegación entre letras son anclas — un `?letter=a` por
     * ciudad serían 26 URLs × 18.361 ciudades, que es exactamente el problema
     * que el sitio está tratando de sacarse de encima (18,28 M de URLs
     * descubiertas contra 26.335 indexadas).
     *
     * Ordena alfabético y no por contador a propósito: 1.017.913 de las
     * 1.059.464 filas tienen el contador en 0, así que ordenar por popularidad
     * es ordenar por ruido.
     *
     * Cada letra trae las suyas y además cuántas hay en total, para que la
     * ficha pueda decir "120 of 348" en vez de dar a entender que esas son
     * todas.
     *
     * @return Collection<string, array{calles: Collection<int, string>, total: int}>
     */
    public static function indiceDeCiudad(int $ciudadId, ?string $paisId = null): Collection
    {
        $porTipo = Idioma::cubre($paisId);

        $calles = static::query()
            ->where('ciudad_id', $ciudadId)
            ->orderBy('nombre_calle')
            ->limit(self::TOPE_DE_LECTURA)
            ->pluck('nombre_calle')
            ->map(fn (string $nombre) => trim($nombre))
            ->filter(fn (string $nombre) => self::inicial($nombre) !== null)
            ->map(fn (string $nombre) => self::mostrar($nombre))
            ->unique();

        $grupos = $calles
            ->groupBy(fn (string $nombre) => $porTipo
                ? (TipoDeVia::de($nombre) ?? self::inicial($nombre))
                : self::inicial($nombre))
            ->map(fn (Collection $delGrupo, string $clave) => [
                'tipo' => $porTipo && in_array($clave, TipoDeVia::CANONICOS, true),
                'calles' => $delGrupo
                    ->map(fn (string $nombre) => $porTipo && TipoDeVia::de($nombre) !== null
                        ? TipoDeVia::resto($nombre)
                        : $nombre)
                    ->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))
                    ->take(self::CALLES_POR_LETRA)
                    ->values(),
                'total' => $delGrupo->count(),
            ]);

        return $grupos->sortBy(fn (array $grupo, string $clave) => self::ordenDelGrupo($clave, $grupo['tipo']));
    }

    /**
     * En qué orden va cada grupo del índice.
     *
     * Los tipos de vía primero y en el orden en que los declara `TipoDeVia`
     * —que es por cuánto pesan, no alfabético: «Calle» y «Carrera» son el 70%
     * del corpus colombiano—, después `#` y después las letras. Así lo
     * primero que ve el visitante de una ficha colombiana es la puerta por la
     * que entra: «Calle», «Carrera».
     */
    private static function ordenDelGrupo(string $clave, bool $esTipo): string
    {
        /*
         * Los prefijos son letras y no dígitos a propósito: PHP compara dos
         * cadenas numéricas como números, así que con '1000' y '2' el grupo
         * '#' se colaba antes que los tipos de vía. Con 'a', 'b' y 'c' la
         * comparación es de texto y el orden es el que dice esta función.
         */
        if ($esTipo) {
            $posicion = array_search($clave, TipoDeVia::CANONICOS, true);

            return 'a'.str_pad((string) $posicion, 3, '0', STR_PAD_LEFT);
        }

        return $clave === '#' ? 'b' : 'c'.$clave;
    }

    /**
     * La letra bajo la que va una calle, o null si no entra en el índice.
     *
     * Todo lo que no arranca con letra o dígito queda afuera: el orden
     * alfabético empieza con una fila de nombre vacío, `--102 scott st` y
     * `-1500 weber st e`, que son direcciones mal tecleadas. Un índice que
     * abre con eso se lee como una página rota.
     *
     * Los números sí entran, bajo `#`: en Canadá son calles de verdad
     * —`14 Ave SW`, `6th ave w`— y son 5.882 en el mercado elegido.
     */
    public static function inicial(string $nombre): ?string
    {
        $primera = Str::upper(Str::substr(Str::ascii(trim($nombre)), 0, 1));

        if ($primera === '') {
            return null;
        }

        if (ctype_digit($primera)) {
            return '#';
        }

        return ctype_alpha($primera) ? $primera : null;
    }

    /**
     * El nombre listo para mostrar.
     *
     * Están guardados en minúscula, así que sin esto el índice sale entero en
     * minúsculas. Los direccionales vuelven a mayúscula porque en Canadá son
     * parte del nombre y `Str::title` los deja como "Sw".
     */
    public static function mostrar(string $nombre): string
    {
        $titulado = Str::title(trim($nombre));

        // Str::title capitaliza después de cualquier carácter que no sea
        // letra, incluidos los dígitos: deja "6th ave" como "6Th Ave".
        $titulado = preg_replace_callback(
            '/(\d)(\p{Lu})/u',
            fn (array $m) => $m[1].Str::lower($m[2]),
            $titulado
        ) ?? $titulado;

        return preg_replace_callback(
            '/\b(n|s|e|w|ne|nw|se|sw)\b/i',
            fn (array $m) => Str::upper($m[1]),
            $titulado
        ) ?? $titulado;
    }

    /**
     * Las calles más buscadas de una ciudad.
     */
    public function scopeTopDeCiudad(Builder $query, int $ciudadId, int $limite = 20): Builder
    {
        return $query->where('ciudad_id', $ciudadId)
            ->orderByDesc('cantidad_busquedas')
            ->limit($limite);
    }

    /**
     * Normaliza el nombre de calle quitando espacios, para deduplicar.
     */
    public static function compactar(string $nombreCalle): string
    {
        return str_replace(' ', '', $nombreCalle);
    }

    /**
     * Deja el nombre de calle listo para guardar.
     *
     * Los datos importados traen algunos restos de lo que la gente pegó en el
     * buscador viejo: caracteres de control, un BOM suelto, espacios dobles y
     * acentos en forma descompuesta. Nada de eso se ve, pero ensucia el
     * ranking y hace que la misma calle se vea escrita distinto.
     */
    public static function limpiar(string $nombreCalle): string
    {
        // Controles C0/C1, BOM y caracteres de ancho cero.
        $limpio = preg_replace(
            '/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{200B}-\x{200F}\x{FEFF}]/u',
            '',
            $nombreCalle
        );

        $limpio = preg_replace('/\s+/u', ' ', $limpio);

        /*
         * A forma compuesta, para que un "José" tecleado con acento combinante
         * quede byte a byte igual al "José" precompuesto que trajo el import.
         *
         * `Normalizer` viene de la extensión `intl`, y esta función corre en
         * las DOS rutas públicas que guardan una calle. En un hosting
         * compartido donde `intl` no esté activada, llamarla a secas es un
         * error fatal en la función principal del sitio, y el síntoma sería
         * que el buscador de direcciones deja de responder sin más pista.
         *
         * Con la guarda, la ausencia de `intl` cuesta lo que tiene que
         * costar: los acentos descompuestos no se unifican. Es un defecto de
         * calidad en un puñado de nombres, no una caída.
         */
        if (class_exists(\Normalizer::class)) {
            $limpio = \Normalizer::normalize($limpio, \Normalizer::FORM_C) ?: $limpio;
        }

        return trim($limpio);
    }
}
