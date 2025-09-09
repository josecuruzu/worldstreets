<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Busqueda extends Model
{
    use HasFactory;

    protected $table = 'busquedas';

    public $incrementing = false;
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'ciudad_id',
        'nombre_calle',
        'nombre_calle_compacto',
        'cantidad_busquedas'
    ];
}