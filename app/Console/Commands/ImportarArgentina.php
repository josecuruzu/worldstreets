<?php

namespace App\Console\Commands;

use App\Models\Ciudad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Trae los datos de calles-argentinas al esquema global de worldstreets.
 *
 * El origen usa el modelo viejo (una sola tabla de ciudades colgando de
 * provincias, ids enteros); el destino está calcado de los dumps de GeoNames.
 * El grueso del trabajo de este comando es traducir entre los dos.
 *
 * Convención de coordenadas heredada del origen y respetada acá:
 * centro_x = LATITUD, centro_y = LONGITUD.
 */
class ImportarArgentina extends Command
{
    protected $signature = 'worldstreets:importar-argentina
                            {--source=josecuruzu_carg : Schema MySQL de origen}
                            {--fresh : Vacía las tablas de destino antes de importar}';

    protected $description = 'Importa países, provincias, departamentos, ciudades y búsquedas desde la base de calles-argentinas';

    /** El origen solo tiene Argentina; su tabla paises es (id, nombre). */
    private const PAIS_ID = 'AR';

    public function handle(): int
    {
        $origen = $this->option('source');

        if (! preg_match('/^[A-Za-z0-9_]+$/', $origen)) {
            $this->error("Nombre de schema inválido: {$origen}");

            return self::FAILURE;
        }

        if (! $this->schemaExiste($origen)) {
            $this->error("El schema '{$origen}' no existe en esta conexión.");

            return self::FAILURE;
        }

        $this->info("Origen: {$origen}  →  Destino: ".DB::getDatabaseName());
        $this->newLine();

        if ($this->option('fresh')) {
            $this->vaciarDestino();
        }

        $this->call('db:seed', ['--class' => 'ContinentesSeeder', '--force' => true]);

        $this->importarPais();
        $this->importarProvincias($origen);
        $this->importarDepartamentos($origen);
        $this->importarCiudades($origen);
        $this->importarBusquedas($origen);

        $this->newLine();
        $this->resumen();

        return self::SUCCESS;
    }

    private function schemaExiste(string $schema): bool
    {
        return DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$schema]
        ) !== null;
    }

    private function vaciarDestino(): void
    {
        // De hijas a padres. No hay foreign keys declaradas, pero el orden
        // deja la base consistente si el comando se corta a la mitad.
        foreach (['busquedas', 'ciudades', 'departamentos', 'provincias', 'paises'] as $tabla) {
            DB::table($tabla)->truncate();
        }

        $this->warn('Tablas de destino vaciadas (--fresh).');
        $this->newLine();
    }

    /**
     * El origen solo guarda id y nombre del país, así que el resto de la ficha
     * son los valores de referencia ISO / GeoNames para Argentina.
     */
    private function importarPais(): void
    {
        DB::table('paises')->upsert([[
            'id' => self::PAIS_ID,
            'iso_alpha3' => 'ARG',
            'iso_numeric' => 32,
            'fips_code' => 'AR',
            'nombre' => 'Argentina',
            'url' => 'argentina',
            'capital' => 'Buenos Aires',
            'areainsqkm' => 2766890,
            'population' => 44938712,
            'continente_id' => 'SA',
            'tld' => '.ar',
            'currency' => 'ARS',
            'currencyName' => 'Peso',
            'Phone' => '54',
            'postalCodeFormat' => '@####@@@',
            'postalCodeRegex' => '^([A-Z]{1}\d{4}[A-Z]{3})$',
            'geonameId' => 3865483,
            'languages' => 'es-AR,en,it,de,fr,gn',
            'neighbours' => 'CL,BO,UY,PY,BR',
            'equivalentFipsCode' => null,
            'visitas' => 0,
            'centro_x' => -38.41610,
            'centro_y' => -63.61670,
            'zoom' => 4,
        ]], ['id']);

        $this->line('  paises .............. 1');
    }

    private function importarProvincias(string $origen): void
    {
        // El destino no tiene columna zoom en provincias: ese dato se pierde.
        $filas = DB::table("{$origen}.provincias")->orderBy('id')->get()->map(fn ($p) => [
            'id' => (string) $p->id,
            'nombre' => $p->nombre,
            'alias' => $p->nombre,
            'geonameid' => null,
            'url' => $p->url,
            'pais_id' => self::PAIS_ID,
            'centro_x' => $p->centro_x,
            'centro_y' => $p->centro_y,
        ])->all();

        DB::table('provincias')->upsert($filas, ['id']);

        $this->line('  provincias .......... '.count($filas));
    }

    /**
     * El origen no tiene tabla de departamentos: solo un código INDEC suelto en
     * cada ciudad (que es justo lo que GeoNames usa como admin2). Se reconstruye
     * la tabla a partir de esos códigos y se calcula el centro promediando las
     * ciudades que cuelgan de cada uno. Los nombres quedan en NULL porque no
     * hay ninguna fuente para ellos.
     */
    private function importarDepartamentos(string $origen): void
    {
        $filas = DB::table("{$origen}.ciudades")
            ->selectRaw('departamento, provincia_id, AVG(centro_x) AS cx, AVG(centro_y) AS cy')
            ->whereNotNull('departamento')
            ->where('departamento', '>', 0)
            ->groupBy('departamento', 'provincia_id')
            ->get()
            ->map(fn ($d) => [
                'id' => (string) $d->departamento,
                'nombre' => null,
                'alias' => null,
                'geonameid' => null,
                'url' => null,
                'provincia_id' => (string) $d->provincia_id,
                'pais_id' => self::PAIS_ID,
                'centro_x' => round((float) $d->cx, 7),
                'centro_y' => round((float) $d->cy, 7),
            ])->all();

        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('departamentos')->upsert($lote, ['id']);
        }

        $this->line('  departamentos ....... '.count($filas).'  (sin nombre: no hay fuente)');
    }

    private function importarCiudades(string $origen): void
    {
        $total = 0;
        $sinDepartamento = 0;

        $barra = $this->output->createProgressBar(
            DB::table("{$origen}.ciudades")->count()
        );
        $barra->start();

        DB::table("{$origen}.ciudades")->orderBy('id')->chunk(500, function ($ciudades) use (&$total, &$sinDepartamento, $barra) {
            $filas = [];

            foreach ($ciudades as $c) {
                // departamento es int(11) en el origen y usa 0 (además de NULL)
                // como centinela de "sin departamento". Ambos se normalizan a
                // vacío para no dejar ciudades apuntando a un departamento
                // que no existe.
                $departamento = ($c->departamento === null || (int) $c->departamento === 0)
                    ? ''
                    : (string) $c->departamento;

                if ($departamento === '') {
                    $sinDepartamento++;
                }

                $filas[] = [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'alias' => $c->alias,
                    // Clave de búsqueda: sin acentos y en minúsculas.
                    'nombre_min' => Ciudad::normalizar((string) $c->nombre),
                    'url' => $c->url,
                    'alternatenames' => $c->tags ?: null,
                    'centro_x' => $c->centro_x,
                    'centro_y' => $c->centro_y,
                    'limite_sw_x' => $c->limite_sw_x,
                    'limite_sw_y' => $c->limite_sw_y,
                    'limite_ne_x' => $c->limite_ne_x,
                    'limite_ne_y' => $c->limite_ne_y,
                    'zoom' => $c->zoom,
                    // tipo del origen ya es un feature code de GeoNames (PPL,
                    // PPLA2, …); su primera letra es la feature class.
                    'fclass' => Str::substr((string) $c->tipo, 0, 1) ?: null,
                    'fcode' => $c->tipo,
                    'pais_id' => self::PAIS_ID,
                    'cc2' => null,
                    'admin1' => null,
                    'admin2' => $departamento ?: null,
                    'admin3' => null,
                    'admin4' => null,
                    // TODO: worldstreets agregó "clase" y no existe en el
                    // origen ni está definida su semántica. Se copia fcode
                    // para no dejar la columna NOT NULL vacía.
                    'clase' => $c->tipo,
                    'provincia_id' => (string) $c->provincia_id,
                    'departamento_id' => $departamento,
                    'population' => null,
                    'elevation' => null,
                    'gtopo30' => null,
                    'timezone' => null,
                    'moddate' => null,
                    'visitas' => $c->visitas ?? 0,
                    'updated_at' => $c->updated_at,
                ];
            }

            DB::table('ciudades')->upsert($filas, ['id']);

            $total += count($filas);
            $barra->advance(count($filas));
        });

        $barra->finish();
        $this->newLine();
        $this->line("  ciudades ............ {$total}  (sin departamento: {$sinDepartamento})");
    }

    /**
     * 400k filas con las mismas columnas de un lado y del otro: se copian
     * dentro de MySQL en vez de traerlas a PHP.
     */
    private function importarBusquedas(string $origen): void
    {
        $columnas = 'id, ciudad_id, nombre_calle, nombre_calle_compacto, cantidad_busquedas, created_at, updated_at';

        // Guarda por si el origen llegara a superar el varchar(255) del destino.
        $descartadas = (int) DB::table("{$origen}.busquedas")
            ->whereRaw('CHAR_LENGTH(nombre_calle) > 255 OR CHAR_LENGTH(nombre_calle_compacto) > 255')
            ->count();

        DB::statement("
            INSERT INTO busquedas ({$columnas})
            SELECT {$columnas} FROM {$origen}.busquedas
            WHERE CHAR_LENGTH(nombre_calle) <= 255
              AND CHAR_LENGTH(nombre_calle_compacto) <= 255
            ON DUPLICATE KEY UPDATE
                ciudad_id             = VALUES(ciudad_id),
                nombre_calle          = VALUES(nombre_calle),
                nombre_calle_compacto = VALUES(nombre_calle_compacto),
                cantidad_busquedas    = VALUES(cantidad_busquedas),
                created_at            = VALUES(created_at),
                updated_at            = VALUES(updated_at)
        ");

        $this->line('  busquedas ........... '.DB::table('busquedas')->count()
            .($descartadas > 0 ? "  (descartadas por largo: {$descartadas})" : ''));
    }

    private function resumen(): void
    {
        $this->table(
            ['Tabla', 'Filas'],
            collect(['continentes', 'paises', 'provincias', 'departamentos', 'ciudades', 'busquedas'])
                ->map(fn ($t) => [$t, number_format(DB::table($t)->count())])
                ->all()
        );
    }
}
