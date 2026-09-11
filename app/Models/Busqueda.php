<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

        // A forma compuesta, para que un "José" tecleado con acento combinante
        // quede byte a byte igual al "José" precompuesto que trajo el import.
        $limpio = \Normalizer::normalize($limpio, \Normalizer::FORM_C) ?: $limpio;

        return trim($limpio);
    }
}
