<?php

namespace App\Models\HRControl;

use Database\Factories\HRControl\RutaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $idruta
 * @property string|null $placa
 * @property string|null $piloto
 * @property string|null $copiloto
 * @property string|null $precintos
 * @property string|null $carreta
 * @property string|null $estado
 */
class Ruta extends Model
{
    /** @use HasFactory<RutaFactory> */
    use HasFactory;

    /**
     * Hoja de ruta abierta (en curso).
     */
    public const ACTIVA = 'A';

    /**
     * Hoja de ruta cerrada: un documento con `condicionaFin = 1` la finalizó.
     */
    public const FINALIZADA = 'F';

    protected $table = 'ruta';

    protected $primaryKey = 'idruta';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'idruta',
        'placa',
        'piloto',
        'copiloto',
        'precintos',
        'carreta',
        'estado',
    ];

    /**
     * @return HasMany<DetalleRuta, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleRuta::class, 'ruta_idruta', 'idruta');
    }

    /**
     * Siguiente código correlativo para una hoja de ruta: T000001, T000002, …
     */
    public static function siguienteCodigo(): string
    {
        $ultimo = static::query()
            ->where('idruta', 'like', 'T%')
            ->orderByRaw('LENGTH(idruta) DESC')
            ->orderByDesc('idruta')
            ->value('idruta');

        $n = is_string($ultimo) && ctype_digit(substr($ultimo, 1))
            ? ((int) substr($ultimo, 1)) + 1
            : 1;

        return 'T'.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    /**
     * ID de la hoja de ruta con un tramo abierto (EN RUTA) para esta placa
     * ahora mismo, sin importar qué conductor la inició, o `null` si la
     * unidad está libre para empezar una hoja desde cero.
     */
    public static function idEnRutaPorPlaca(string $placa): ?string
    {
        return static::query()
            ->where('placa', $placa)
            ->whereHas('detalles', fn ($q) => $q->where('estado', DetalleRuta::EN_RUTA))
            ->value('idruta');
    }
}
