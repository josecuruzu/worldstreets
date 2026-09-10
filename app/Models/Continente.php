<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Continente extends Model
{
    use HasFactory;

    protected $table = 'continentes';

    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nombre',
        'geonameid',
        'url',
        'centro_x',
        'centro_y',
        'zoom',
        'descripcion',
    ];

    protected $casts = [
        'geonameid' => 'integer',
        'centro_x' => 'decimal:5',
        'centro_y' => 'decimal:5',
        'zoom' => 'integer',
    ];

    public function paises(): HasMany
    {
        return $this->hasMany(Pais::class, 'continente_id');
    }
}
