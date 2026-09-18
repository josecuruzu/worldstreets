<?php

use App\Models\Busqueda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
 * El dataset global trae los nombres de calle tal como los tecleó la gente en
 * el sitio viejo: 33.241 filas con espacios de más, 336 con caracteres de
 * control, 14 con BOM o caracteres de ancho cero y unas pocas con los acentos
 * en forma descompuesta. Nada de eso se ve, pero sale impreso en el ranking de
 * la home y hace que la misma calle aparezca escrita de dos maneras.
 *
 * `Busqueda::limpiar()` ya es el criterio con el que entra todo lo que se busca
 * desde el sitio; acá se le aplica a lo que vino del import. La migración
 * 2026_09_11_120000 hizo esto mismo con los datos argentinos, donde eran 8
 * filas; con el dataset global hay dos diferencias que obligan a más cuidado:
 *
 * - Limpiar el nombre cambia también su versión compacta, que es la mitad del
 *   índice único `busqueda_unica_por_ciudad`. Dos filas de la misma ciudad
 *   pueden terminar chocando, así que se fusionan sumando los contadores en la
 *   dominante — la más buscada, y el id menor desempata —, que es el mismo
 *   criterio de f835a98.
 * - Antes de tocar nada se deja un respaldo de las filas originales en
 *   storage/app/backups/, porque el nombre viejo no se puede reconstruir.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * El prefiltro usa REGEXP, que SQLite no trae. No es una limitación:
         * en los tests la tabla arranca vacía, así que no hay nada que limpiar.
         */
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $originales = $this->candidatas()->get();

        if ($originales->isEmpty()) {
            return;
        }

        $this->respaldar($originales);

        foreach ($originales->chunk(500) as $tanda) {
            DB::transaction(function () use ($tanda) {
                foreach ($tanda as $fila) {
                    $this->limpiarFila($fila);
                }
            });
        }
    }

    public function down(): void
    {
        // Los caracteres removidos no se reponen, y las filas fusionadas
        // quedaron sumadas en la dominante. El respaldo en storage/app/backups
        // es lo que queda del estado anterior.
    }

    /**
     * Filas que podrían necesitar limpieza. Es un prefiltro amplio a propósito:
     * pescar de más no cuesta nada — `limpiar()` las deja igual y se descartan
     * enseguida —, pero pescar de menos dejaría basura adentro.
     *
     * Va por SQL y no fila por fila porque la tabla tiene más de un millón de
     * registros y los sucios son apenas el 3%.
     */
    private function candidatas(): Builder
    {
        return DB::table('busquedas')
            ->select(['id', 'ciudad_id', 'nombre_calle', 'nombre_calle_compacto', 'cantidad_busquedas'])
            ->where(function (Builder $query) {
                $query
                    /*
                     * BINARY no es adorno: utf8mb4_unicode_ci es una collation
                     * PAD SPACE, así que `nombre_calle <> TRIM(nombre_calle)`
                     * da falso cuando la única diferencia es un espacio al
                     * final. Sin el BINARY, esta migración se salteaba 4.858
                     * filas — y una verificación escrita igual las daba por
                     * limpias.
                     */
                    ->whereRaw('BINARY nombre_calle <> BINARY TRIM(nombre_calle)')
                    ->orWhere('nombre_calle', 'like', '%  %')
                    ->orWhereRaw("nombre_calle REGEXP '[[:cntrl:]]'")
                    // BOM, caracteres de ancho cero y marcas de combinación
                    // (los acentos descompuestos), por sus bytes en UTF-8.
                    ->orWhereRaw("HEX(nombre_calle) REGEXP 'EFBBBF|E2808[BCDEF]|CC[89AB]|CD[89AF]'");
            })
            ->orderBy('id');
    }

    private function limpiarFila(object $fila): void
    {
        $limpio = Busqueda::limpiar($fila->nombre_calle);
        $compacto = Busqueda::compactar($limpio);

        if ($limpio === $fila->nombre_calle && $compacto === $fila->nombre_calle_compacto) {
            return;
        }

        // Un nombre que era solo basura invisible no deja nada que guardar, y
        // `registrar()` tampoco lo aceptaría hoy.
        if ($compacto === '') {
            DB::table('busquedas')->where('id', $fila->id)->delete();

            return;
        }

        $gemela = DB::table('busquedas')
            ->where('ciudad_id', $fila->ciudad_id)
            ->where('nombre_calle_compacto', $compacto)
            ->where('id', '<>', $fila->id)
            ->first();

        if ($gemela === null) {
            DB::table('busquedas')->where('id', $fila->id)->update([
                'nombre_calle' => $limpio,
                'nombre_calle_compacto' => $compacto,
            ]);

            return;
        }

        $this->fusionar($fila, $limpio, $compacto, $gemela);
    }

    /**
     * Al limpiarla, esta fila pasó a ser la misma calle que otra que ya estaba.
     * Gana la más buscada — el id menor desempata — y se queda con la suma.
     */
    private function fusionar(object $fila, string $limpio, string $compacto, object $gemela): void
    {
        $suyas = (int) $fila->cantidad_busquedas;
        $ajenas = (int) $gemela->cantidad_busquedas;
        $total = $suyas + $ajenas;

        $ganaLaGemela = $ajenas > $suyas || ($ajenas === $suyas && (int) $gemela->id < (int) $fila->id);

        if ($ganaLaGemela) {
            DB::table('busquedas')->where('id', $gemela->id)->update(['cantidad_busquedas' => $total]);
            DB::table('busquedas')->where('id', $fila->id)->delete();

            return;
        }

        // Se borra primero la gemela: si no, el índice único rechazaría el
        // update, porque por un instante las dos filas tendrían el mismo par.
        DB::table('busquedas')->where('id', $gemela->id)->delete();
        DB::table('busquedas')->where('id', $fila->id)->update([
            'nombre_calle' => $limpio,
            'nombre_calle_compacto' => $compacto,
            'cantidad_busquedas' => $total,
        ]);
    }

    /**
     * Al lado del respaldo que dejó f835a98. Se escribe con storage_path() y no
     * con el disco `local` porque en Laravel 11+ ese disco apunta a
     * storage/app/private, y los respaldos de esta tabla viven en app/backups.
     */
    private function respaldar(Collection $originales): void
    {
        $carpeta = storage_path('app/backups');
        File::ensureDirectoryExists($carpeta);

        File::put(
            $carpeta.'/busquedas-sucias-'.now()->format('Ymd-His').'.json',
            $originales->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
};
