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
}
