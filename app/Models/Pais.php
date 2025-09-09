<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pais extends Model
{
    use HasFactory;

    protected $table = 'paises';
    public $timestamps = false;
    protected $primaryKey = 'id';
    public $incrementing = false;

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
        'zoom'
    ];
}