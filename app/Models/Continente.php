<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Continente extends Model
{
    use HasFactory;

    protected $table = 'continentes';
    public $timestamps = false;
    protected $primaryKey = 'id';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'nombre',
        'geonameid',
        'url',
        'centro_x',
        'centro_y',
        'zoom',
        'descripcion'
    ];
}