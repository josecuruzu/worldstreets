<?php

use App\Models\Busqueda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Quedaron unas pocas filas con caracteres que no se ven: un carácter de
 * control (U+0011), un BOM suelto (U+FEFF) y varias con acentos en forma
 * descompuesta (`José` como J-o-s-e-U+0301). Las últimas no son un defecto —
 * se leen igual y la collation las matchea bien — pero conviene que toda la
 * tabla esté en la misma forma que usa `Busqueda::limpiar()`, que a partir de
 * ahora se aplica a todo lo que entra por el buscador.
 *
 * Se buscan con un LIKE contra el BOM: en la collation de MySQL esos
 * caracteres son ignorables, así que el patrón los pesca a todos. En SQLite la
 * collation es binaria y no encuentra nada, que es lo correcto: la tabla de
 * los tests arranca vacía.
 */
return new class extends Migration
{
    public function up(): void
    {
        $bom = "\u{FEFF}";

        $filas = DB::table('busquedas')
            ->where('nombre_calle', 'like', '%'.$bom.'%')
            ->get(['id', 'nombre_calle']);

        foreach ($filas as $fila) {
            $limpio = Busqueda::limpiar($fila->nombre_calle);

            if ($limpio === $fila->nombre_calle || $limpio === '') {
                continue;
            }

            DB::table('busquedas')->where('id', $fila->id)->update([
                'nombre_calle' => $limpio,
                'nombre_calle_compacto' => Busqueda::compactar($limpio),
            ]);
        }
    }

    public function down(): void
    {
        // Los caracteres removidos no se pueden reponer, y tampoco hacían falta.
    }
};
