<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ciudad extends Model
{
    use HasFactory;

    protected $table = 'ciudades';

    // La tabla tiene updated_at (con ON UPDATE CURRENT_TIMESTAMP en MySQL)
    // pero no created_at, así que Eloquent no maneja los timestamps.
    public $timestamps = false;

    protected $primaryKey = 'id';

    public $incrementing = false;

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
        'provincia_id',
        'departamento_id',
        'population',
        'elevation',
        'gtopo30',
        'timezone',
        'moddate',
        'visitas',
    ];

    protected $casts = [
        'centro_x' => 'decimal:7',
        'centro_y' => 'decimal:7',
        'limite_sw_x' => 'decimal:7',
        'limite_sw_y' => 'decimal:7',
        'limite_ne_x' => 'decimal:7',
        'limite_ne_y' => 'decimal:7',
        'zoom' => 'integer',
        'population' => 'integer',
        'elevation' => 'integer',
        'gtopo30' => 'integer',
        'visitas' => 'integer',
        'moddate' => 'date',
        'updated_at' => 'datetime',
    ];

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function busquedas(): HasMany
    {
        return $this->hasMany(Busqueda::class, 'ciudad_id');
    }

    /**
     * Clave de búsqueda de una ciudad: minúsculas y sin acentos.
     *
     * Es lo que guarda la columna nombre_min, y lo que hay que aplicarle al
     * término que escribe el usuario para que ambos lados coincidan.
     */
    public static function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii($texto));
    }

    /**
     * Busca por nombre normalizado. Prioriza las que empiezan con el término
     * por sobre las que solo lo contienen ("cordo" -> Córdoba antes que
     * Villa Cordobita), y entre iguales las más visitadas.
     */
    public function scopeBuscar(Builder $query, string $termino): Builder
    {
        $termino = static::normalizar(trim($termino));
        $escapado = addcslashes($termino, '%_\\');

        return $query
            ->where('nombre_min', 'like', "%{$escapado}%")
            ->orderByRaw('nombre_min LIKE ? DESC', ["{$escapado}%"])
            ->orderByDesc('visitas')
            ->orderBy('nombre_min');
    }

    public function scopeByUrl(Builder $query, string $url): Builder
    {
        return $query->where('url', $url);
    }

    public function scopePopulares(Builder $query, int $limite = 15): Builder
    {
        return $query->orderByDesc('visitas')->limit($limite);
    }

    /**
     * Nombre para mostrar: alias si existe, si no el nombre.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->alias ?: $this->nombre;
    }
}
