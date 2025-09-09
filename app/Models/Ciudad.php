<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ciudad extends Model
{
    use HasFactory;

    protected $table = 'ciudades';
    public $timestamps = false;
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'nombre',
        'alias',
        'nombre_min',
        'url',
        'alternatenames',
        'centro_x',
        'centro_y',
        'limite_sw_x',
        'limite_sw_y',
        'limite_ne_x',
        'limite_ne_y',
        'zoom',
        'fclass',
        'fcode',
        'pais_id',
        'cc2',
        'admin1',
        'admin2',
        'admin3',
        'admin4',
        'clase',
        'provincia_id',
        'departamento_id',
        'population',
        'elevation',
        'gtopo30',
        'timezone',
        'moddate',
        'visitas',
    ];
}
