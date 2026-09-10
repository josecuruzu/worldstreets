<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pais extends Model
{
    use HasFactory;

    protected $table = 'paises';

    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'iso_alpha3',
        'iso_numeric',
        'fips_code',
        'nombre',
        'url',
        'capital',
        'areainsqkm',
        'population',
        'continente_id',
        'tld',
        'currency',
        'currencyName',
        'Phone',
        'postalCodeFormat',
        'postalCodeRegex',
        'geonameId',
        'languages',
        'neighbours',
        'equivalentFipsCode',
        'visitas',
        'centro_x',
        'centro_y',
        'zoom',
    ];

    protected $casts = [
        'iso_numeric' => 'integer',
        'areainsqkm' => 'float',
        'population' => 'integer',
        'geonameId' => 'integer',
        'visitas' => 'integer',
        'centro_x' => 'decimal:5',
        'centro_y' => 'decimal:5',
        'zoom' => 'integer',
    ];

    public function continente(): BelongsTo
    {
        return $this->belongsTo(Continente::class, 'continente_id');
    }

    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class, 'pais_id');
    }

    public function ciudades(): HasMany
    {
        return $this->hasMany(Ciudad::class, 'pais_id');
    }

    public function scopeByUrl(Builder $query, string $url): Builder
    {
        return $query->where('url', $url);
    }
}
