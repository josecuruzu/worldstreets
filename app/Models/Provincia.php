<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
