<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Provincia extends Model
{
    use HasFactory;

    protected $table = 'provincias';

    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nombre',
        'alias',
        'geonameid',
        'url',
        'pais_id',
        'centro_x',
        'centro_y',
    ];

    protected $casts = [
        'geonameid' => 'integer',
        'centro_x' => 'decimal:7',
        'centro_y' => 'decimal:7',
    ];

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function departamentos(): HasMany
    {
        return $this->hasMany(Departamento::class, 'provincia_id');
    }

    public function ciudades(): HasMany
    {
        return $this->hasMany(Ciudad::class, 'provincia_id');
    }

    public function scopeByUrl(Builder $query, string $url): Builder
    {
        return $query->where('url', $url);
    }

    /**
     * Segmento de URL dentro del país: `/japan/tokyo`.
     *
     * No se usa la columna `url`, que trae el país pegado ("tokyo-japan") y en
     * una ruta anidada lo repetiría. Dos provincias del dataset no tienen
     * nombre, así que ahí se cae al id, que siempre está.
     */
    public function getSlugAttribute(): string
    {
        return Str::slug((string) $this->nombre) ?: Str::slug($this->id);
    }
}
