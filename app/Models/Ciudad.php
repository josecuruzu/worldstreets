<?php

namespace App\Models;

use App\Support\Idioma;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Ciudad extends Model
{
    use HasFactory;

    protected $table = 'ciudades';

    /**
     * Palabra más corta que indexa el fulltext de InnoDB
     * (`innodb_ft_min_token_size`, que por defecto vale 3 tanto en MySQL como
     * en MariaDB). Por debajo de eso el buscador cae al índice B-tree.
     *
     * Se mide en bytes y no en caracteres, que es lo que hace que funcionen los
     * alfabetos no latinos: "東京" son dos caracteres pero seis bytes, y está
     * indexado — probado contra la base, devuelve Tokyo.
     */
    private const MIN_PALABRA_FULLTEXT = 3;

    /**
     * Cuántas coincidencias del fulltext entran al ranking fino. Se toman las
     * más visitadas: con un término ancho las coincidencias son decenas de
     * miles y ordenarlas todas es lo que hacía lenta la consulta.
     */
    private const CANDIDATOS_FULLTEXT = 200;

    // La tabla tiene updated_at (con ON UPDATE CURRENT_TIMESTAMP en MySQL)
    // pero no created_at, así que Eloquent no maneja los timestamps.
    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'nombre',
        'alias',
        'nombre_min',
        'url',
        'alternatenames',
        'centro_x',
        'centro_y',
        'limite_sw_x',
        'limite_sw_y',
        'limite_ne_x',
        'limite_ne_y',
        'zoom',
        'fcode',
        'pais_id',
        'admin2',
        'provincia_id',
        'departamento_id',
        'population',
        'visitas',
    ];

    protected $casts = [
        'centro_x' => 'decimal:7',
        'centro_y' => 'decimal:7',
        'limite_sw_x' => 'decimal:7',
        'limite_sw_y' => 'decimal:7',
        'limite_ne_x' => 'decimal:7',
        'limite_ne_y' => 'decimal:7',
        'zoom' => 'integer',
        'population' => 'integer',
        'visitas' => 'integer',
        'tiene_calles' => 'boolean',
        'updated_at' => 'datetime',
    ];

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function busquedas(): HasMany
    {
        return $this->hasMany(Busqueda::class, 'ciudad_id');
    }

    /**
     * Clave de búsqueda de una ciudad: minúsculas y sin acentos.
     *
     * Es lo que guarda la columna nombre_min, y lo que hay que aplicarle al
     * término que escribe el usuario para que ambos lados coincidan.
     */
    public static function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii($texto));
    }

    /**
     * Busca por nombre normalizado, por prefijo de palabra: "cordo" encuentra
     * Córdoba y "york" encuentra New York City, pero "ordo" ya no encuentra
     * Córdoba. Antes era `LIKE '%termino%'`, que con las 4,7 M de ciudades del
     * dataset global costaba un full scan de 8 segundos por tecla.
     *
     * Prioriza las que empiezan con el término por sobre las que lo tienen más
     * adentro ("cordo" -> Córdoba antes que Villa Cordobita), y entre iguales
     * manda la población, con las visitas como desempate.
     *
     * El orden era al revés — visitas primero — y con los datos argentinos
     * funcionaba, pero contra el dataset global no: solo 11.875 ciudades de
     * 4,7 M tienen visitas > 0, así que para el 99,75% el desempate terminaba
     * siendo alfabético. Santiago de Cuba (117 visitas) le ganaba a Santiago de
     * Chile (23 visitas, 4,8 M de habitantes y capital), y ni San Jose de
     * California ni San José de Costa Rica entraban en los tres primeros. La
     * población está cargada en 382.539 ciudades, y son justo las grandes.
     */
    public function scopeBuscar(Builder $query, string $termino): Builder
    {
        $palabras = static::palabras($termino);

        if ($palabras === []) {
            return $query->whereRaw('1 = 0');
        }

        $conexion = $query->getConnection();
        $esMysql = $conexion->getDriverName() === 'mysql';

        /*
         * `nombre_min` está plegado a ASCII y sin acentos, así que el camino por
         * LIKE y el orden por prefijo trabajan con el término plegado también.
         * El fulltext no: consulta además `alternatenames`, que guarda los
         * nombres como se escriben en cada idioma, y ahí el término va entero.
         * Plegar "東京" lo dejaría en nada.
         */
        $plegado = static::normalizar(implode(' ', $palabras));
        $escapado = addcslashes($plegado, '%_\\');

        $expresion = $esMysql
            ? static::expresionFulltext($palabras, static::stopwordsDelIndice($conexion))
            : null;

        if ($expresion !== null) {
            /*
             * El índice `ciudades_nombres_fulltext` devuelve todas las
             * coincidencias, y para un término ancho son muchísimas: "+san*"
             * son 95.424 ciudades. Ordenar eso por prefijo tardaba 4,3 s, así
             * que primero se acota a las 200 más pobladas de las que matchean
             * —que el índice resuelve en ~700 ms en el peor caso— y el ranking
             * fino se hace sobre esas.
             */
            $candidatos = static::query()
                ->select('id')
                ->whereRaw('MATCH (nombre_min, alternatenames) AGAINST (? IN BOOLEAN MODE)', [$expresion])
                // Mismo criterio que el ranking de abajo. Si acá se eligiera
                // por visitas, una ciudad grande sin tráfico previo —San Jose
                // de California tiene 1 M de habitantes y visitas = 0— no
                // entraría nunca a los 200 y el orden fino no la vería.
                ->orderByDesc('population')
                ->orderByDesc('visitas')
                ->orderBy('id')
                ->limit(self::CANDIDATOS_FULLTEXT);

            $query->whereIn('id', fn ($sub) => $sub->select('id')->fromSub($candidatos, 'candidatos'));
        } elseif (! $esMysql) {
            /*
             * SQLite (los tests): el mismo prefijo de palabra, emulado a mano,
             * sobre las dos columnas que en MySQL cubre el índice fulltext. En
             * `nombre_min` se busca el término plegado, porque la columna está
             * plegada; en `alternatenames` va tal cual lo escribió el usuario,
             * que es como están guardados los nombres locales. Ahí los nombres
             * se separan por coma.
             */
            $crudo = addcslashes(implode(' ', $palabras), '%_\\');

            $query->where(function (Builder $q) use ($escapado, $crudo) {
                $q->where('nombre_min', 'like', "{$escapado}%")
                    ->orWhere('nombre_min', 'like', "% {$escapado}%")
                    ->orWhere('alternatenames', 'like', "{$crudo}%")
                    ->orWhere('alternatenames', 'like', "% {$crudo}%")
                    ->orWhere('alternatenames', 'like', "%,{$crudo}%");
            });
        } elseif ($plegado === '') {
            // Ni el fulltext pudo tomarlo ni queda nada al plegarlo a ASCII.
            // Sin esto el LIKE de abajo quedaría en `'%'` y devolvería la tabla.
            return $query->whereRaw('1 = 0');
        } else {
            /*
             * Términos de una o dos letras: el fulltext de InnoDB no los indexa
             * (innodb_ft_min_token_size = 3). Se resuelven por rango sobre
             * `ciudades_nombre_min_ranking`, que sí puede con el prefijo. A esa
             * altura del tecleo el resultado es ruido igual, así que no paga un
             * scan de la tabla entera para además mirar dentro del nombre.
             *
             * Va acotado a candidatos por la misma razón que el camino
             * fulltext, y no es una optimización de más: el ORDER BY de abajo
             * mira `alternatenames`, que es TEXT y no entra en el índice, así
             * que sin la subconsulta la base tiene que ir al índice
             * clusterizado de 1,1 GB a buscar esa columna una vez por cada fila
             * del rango. Medido contra los datos reales, mejor de tres en
             * caliente, por cada tecla:
             *
             *     término   sin acotar   acotado
             *     sa          668 ms      84 ms
             *     ba        1.039 ms     123 ms
             *     to          120 ms      17 ms
             *     ne           86 ms      11 ms
             *
             * El conteo de filas leídas NO baja —el rango del índice sigue
             * siendo igual de ancho— y el EXPLAIN de la consulta entera tampoco
             * mejora a simple vista: pasa de `Using index condition; Using
             * filesort` a `Using temporary; Using filesort`, porque ahora
             * materializa la subconsulta.
             *
             * Lo que cambia está adentro. La subconsulta sola explica `Using
             * where; Using index`: recorre el rango sin salir del índice. La
             * tabla de 1,1 GB se toca una vez por cada uno de los 200 que
             * sobreviven, en vez de una vez por cada fila del rango — 162.514
             * para "sa", 254.451 para "ba" —, y esa es toda la diferencia. El
             * `Using temporary` que aparece es la materialización de esos 200.
             *
             * En el hosting compartido, con un buffer pool chico y ajeno, la
             * diferencia es bastante mayor que 8×: cada una de esas lecturas de
             * tabla que se ahorran puede ser un acceso a disco.
             */
            $candidatos = static::query()
                ->select('id')
                ->where('nombre_min', 'like', "{$escapado}%")
                // Mismo criterio que el ranking de abajo, para que una ciudad
                // grande no se quede afuera de los 200 antes del orden fino.
                ->orderByDesc('population')
                ->orderByDesc('visitas')
                ->orderBy('id')
                ->limit(self::CANDIDATOS_FULLTEXT);

            $query->whereIn('id', fn ($sub) => $sub->select('id')->fromSub($candidatos, 'candidatos'));
        }

        [$empiezaCon, $patrones] = static::empiezaConElTermino($escapado, addcslashes(implode(' ', $palabras), '%_\\'));

        return $query
            ->orderByRaw("({$empiezaCon}) DESC", $patrones)
            ->orderByDesc('population')
            ->orderByDesc('visitas')
            ->orderBy('nombre_min');
    }

    /**
     * La condición que sube a los que empiezan con el término buscado.
     *
     * Mira las dos columnas por las que se busca, no solo `nombre_min`. Cuando
     * miraba una sola, quien escribía el nombre local de una ciudad grande caía
     * en un homónimo minúsculo: "Tokio" devolvía un paraje de Bolivia —que en
     * inglés se llama así— antes que Tokyo, porque Tokyo solo podía coincidir
     * por `alternatenames` y por ahí no había manera de ganarse el empujón.
     *
     * @return array{string, list<string>}
     */
    private static function empiezaConElTermino(string $plegado, string $crudo): array
    {
        $condiciones = [];
        $patrones = [];

        // `nombre_min` está plegado a ASCII; si el término no deja nada al
        // plegarlo, por esa columna no hay nada que comparar.
        if ($plegado !== '') {
            $condiciones[] = 'nombre_min LIKE ?';
            $patrones[] = "{$plegado}%";
        }

        // En `alternatenames` los nombres van separados por coma, así que
        // "empieza" es tanto el principio de la lista como el de cualquier
        // nombre de adentro.
        $condiciones[] = 'alternatenames LIKE ?';
        $patrones[] = "{$crudo}%";
        $condiciones[] = 'alternatenames LIKE ?';
        $patrones[] = "%,{$crudo}%";

        return [implode(' OR ', $condiciones), $patrones];
    }

    /**
     * Parte el término en palabras de letras y números.
     *
     * Es el mismo criterio con el que el fulltext corta el texto que indexa, y
     * de paso sanea: al quedarse solo con letras y números descarta lo que en
     * modo booleano son operadores (+ - * " ~ () @ < >) y que si no reventarían
     * la consulta.
     *
     * `\p{L}` y no `a-z`: el término puede venir en cualquier alfabeto, porque
     * desde que el índice cubre `alternatenames` se busca por el nombre local.
     * Con `a-z` un término en cirílico o en árabe quedaba en cero palabras.
     *
     * @return list<string>
     */
    private static function palabras(string $termino): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', Str::lower($termino), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Arma la expresión booleana del fulltext: cada palabra pasa a ser
     * obligatoria y abierta por derecha ("new yor" -> "+new* +yor*").
     *
     * Devuelve null si no queda ninguna palabra que el índice pueda resolver,
     * y ahí `scopeBuscar()` cae al B-tree.
     *
     * @param  list<string>  $palabras
     * @param  list<string>  $stopwords
     */
    private static function expresionFulltext(array $palabras, array $stopwords): ?string
    {
        $utiles = array_filter($palabras, fn (string $palabra) => strlen($palabra) >= self::MIN_PALABRA_FULLTEXT
            && ! in_array($palabra, $stopwords, true));

        if ($utiles === []) {
            return null;
        }

        return implode(' ', array_map(fn (string $palabra) => "+{$palabra}*", $utiles));
    }

    /**
     * Las palabras que el índice fulltext no guarda.
     *
     * Hay que sacarlas de la expresión: si una va como obligatoria, y el índice
     * no la tiene, la consulta entera no devuelve nada. Con "the hague" pasaba
     * exactamente eso — "the" es stopword — y la ciudad no aparecía, aunque
     * "+hague*" sola la encuentra.
     *
     * La lista se le pregunta al servidor en vez de quedar escrita acá, porque
     * es configurable (`innodb_ft_server_stopword_table`) y difiere entre
     * MySQL y MariaDB.
     *
     * @return list<string>
     */
    private static function stopwordsDelIndice(Connection $conexion): array
    {
        return Cache::rememberForever('ciudades.stopwords', function () use ($conexion) {
            try {
                return $conexion->table('information_schema.innodb_ft_default_stopword')
                    ->pluck('value')
                    ->all();
            } catch (QueryException) {
                // Servidor sin esa vista: se sigue sin filtrar, que es como
                // venía funcionando.
                return [];
            }
        });
    }

    public function scopeByUrl(Builder $query, string $url): Builder
    {
        return $query->where('url', $url);
    }

    public function scopePopulares(Builder $query, int $limite = 15): Builder
    {
        return $query->orderByDesc('visitas')->limit($limite);
    }

    /**
     * Las ciudades que tienen una página: las que alguien ya usó para buscar
     * una calle.
     *
     * De las 4.697.141 del dataset son 18.361. El resto es un punto en el mapa,
     * y publicarlas como páginas es lo que dejó al sitio con 18,28 M de URLs
     * descubiertas y 26.335 indexadas. No desaparecen del producto: el buscador
     * las sigue encontrando y muestra su mapa. Lo que no tienen es página
     * propia, y la primera vez que alguien busque una calle ahí, la ganan.
     *
     * Se apoya en la columna `tiene_calles` y no en un EXISTS porque las listas
     * ordenan por población: con subconsulta, la ficha de la provincia más
     * grande pasaba de 1 ms a 621 ms.
     */
    public function scopeConCalles(Builder $query): Builder
    {
        return $query->where('tiene_calles', true);
    }

    /**
     * Nombre para mostrar: alias si existe, si no el nombre.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->alias ?: $this->nombre;
    }

    /**
     * Segmento de URL dentro de la provincia: `cordoba-3860259`.
     *
     * Lleva el id pegado a propósito. El slug solo no alcanza para identificar
     * una ciudad —hay 578 llamadas "Krajan" en East Java, y 433.217 grupos de
     * URLs repetidas en total—, así que la ficha se resuelve por id y el slug
     * queda de adorno legible. Si el nombre no deja slug (un nombre en kanji,
     * por ejemplo), el id solo alcanza.
     */
    /**
     * Segmento de provincia cuando la ciudad no tiene una de verdad: 56.761
     * ciudades apuntan al centinela `XX.00` de GeoNames, que no existe como
     * fila en `provincias`. Igual necesitan vivir en algún lugar de la
     * jerarquía, así que van todas acá.
     */
    public const PROVINCIA_DESCONOCIDA = 'unknown-region';

    public function getProvinciaSlugAttribute(): string
    {
        return $this->provincia?->slug ?? self::PROVINCIA_DESCONOCIDA;
    }

    public function getSegmentoAttribute(): string
    {
        $slug = Str::slug($this->display_name);

        return $slug === '' ? (string) $this->id : "{$slug}-{$this->id}";
    }

    /**
     * La URL de la ficha, o null si esta ciudad no tiene una.
     *
     * Devuelve null en dos casos: la ciudad no cuelga de ninguna jerarquía
     * porque le falta el país, o no tiene calles y por lo tanto su ficha
     * responde 410. Es lo que hace que el buscador de la home no ofrezca un
     * enlace a una página muerta: ahí el visitante ve el mapa igual, que es lo
     * que fue a buscar.
     */
    public function rutaDeLaFicha(): ?string
    {
        if ($this->pais?->url === null || ! $this->tiene_calles) {
            return null;
        }

        return Idioma::url('ciudad', [$this->pais->url, $this->provincia_slug, $this->segmento]);
    }

    /**
     * Los nombres de la ciudad en otros idiomas, sin repetir el que ya se
     * muestra. Vienen separados por coma en una sola columna.
     *
     * @return list<string>
     */
    public function nombresLocales(int $limite = 10): array
    {
        $propios = [Str::lower($this->nombre), Str::lower((string) $this->alias)];

        $nombres = collect(explode(',', (string) $this->alternatenames))
            ->map(fn (string $nombre) => trim($nombre))
            ->filter(fn (string $nombre) => $nombre !== '' && ! in_array(Str::lower($nombre), $propios, true))
            ->unique()
            ->take($limite);

        return $nombres->values()->all();
    }
}
