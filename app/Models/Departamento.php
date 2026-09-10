<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    use HasFactory;

    protected $table = 'departamentos';

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
        'provincia_id',
        'pais_id',
        'centro_x',
        'centro_y',
    ];

    protected $casts = [
        'geonameid' => 'integer',
        'centro_x' => 'decimal:7',
        'centro_y' => 'decimal:7',
    ];

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function ciudades(): HasMany
    {
        return $this->hasMany(Ciudad::class, 'departamento_id');
    }
}
