<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Un enlace corto para compartir una dirección encontrada.
 *
 * Ver la migración `2026_09_18_120000` para por qué existe. En resumen: una
 * dirección no tiene URL propia en este sitio y no la va a tener, así que el
 * botón de compartir necesita fabricar una.
 */
class EnlaceCompartido extends Model
{
    /** Tres meses. Un enlace mandado por mensaje se usa en horas. */
    public const DIAS_DE_VIDA = 90;

    /**
     * Diez caracteres del alfabeto de Laravel: suficiente para que no se
     * adivine y corto para que entre en un mensaje sin ocupar la pantalla.
     */
    private const LARGO_DEL_CODIGO = 10;

    public $timestamps = false;

    protected $table = 'enlaces_compartidos';

    protected $fillable = ['codigo', 'ciudad_id', 'calle', 'altura', 'idioma', 'expires_at'];

    protected $casts = [
        'ciudad_id' => 'integer',
        'visitas' => 'integer',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class);
    }

    public function caducado(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * El enlace de una dirección: el que ya existe y sigue vigente, o uno
     * nuevo.
     *
     * Reusar importa. Sin esto, cada clic en "compartir" sobre la misma
     * dirección crea una fila, y el botón se puede apretar muchas veces — es
     * gratis y además da siempre el mismo enlace, que es lo que espera quien
     * lo comparte dos veces.
     */
    public static function paraLaDireccion(int $ciudadId, string $calle, string $altura, string $idioma): self
    {
        $vigente = static::query()
            ->where('ciudad_id', $ciudadId)
            ->where('calle', $calle)
            ->where('altura', $altura)
            ->where('idioma', $idioma)
            ->where('expires_at', '>', now())
            ->first();

        if ($vigente !== null) {
            return $vigente;
        }

        return static::create([
            'codigo' => static::codigoLibre(),
            'ciudad_id' => $ciudadId,
            'calle' => $calle,
            'altura' => $altura,
            'idioma' => $idioma,
            'expires_at' => now()->addDays(self::DIAS_DE_VIDA),
        ]);
    }

    private static function codigoLibre(): string
    {
        do {
            $codigo = Str::random(self::LARGO_DEL_CODIGO);
        } while (static::where('codigo', $codigo)->exists());

        return $codigo;
    }
}
