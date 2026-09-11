<?php

namespace App\Models\HRControl;

use App\Observers\DetalleRutaObserver;
use Database\Factories\HRControl\DetalleRutaFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $iddetalleRuta
 * @property string $ruta_idruta
 * @property int $contacto_idcontacto
 * @property string|null $geocerca
 * @property string|null $coordenada
 * @property string|null $kilometraje
 * @property Carbon|null $fhRegistro
 * @property Carbon|null $fhIndicado
 * @property int|null $orden
 * @property string|null $observacion
 * @property string|null $estado
 */
#[ObservedBy(DetalleRutaObserver::class)]
class DetalleRuta extends Model
{
    /** @use HasFactory<DetalleRutaFactory> */
    use HasFactory;

    /**
     * Estado de una hoja de ruta que sigue en curso.
     */
    public const EN_RUTA = 'ER';

    /**
     * Estado de una hoja de ruta cerrada por el registro de un orden posterior.
     */
    public const FINALIZADO = 'FI';

    protected $table = 'detalleruta';

    protected $primaryKey = 'iddetalleRuta';

    public $timestamps = false;

    protected $fillable = [
        'ruta_idruta',
        'contacto_idcontacto',
        'geocerca',
        'coordenada',
        'kilometraje',
        'fhRegistro',
        'fhIndicado',
        'orden',
        'observacion',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fhRegistro' => 'datetime',
            'fhIndicado' => 'datetime',
            'orden' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Ruta, $this>
     */
    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class, 'ruta_idruta', 'idruta');
    }

    /**
     * @return BelongsTo<Contacto, $this>
     */
    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'contacto_idcontacto', 'idcontacto');
    }

    /**
     * Documentos adjuntos registrados en este orden.
     *
     * @return HasMany<DocRuta, $this>
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(DocRuta::class, 'detalleRuta_iddetalleRuta', 'iddetalleRuta');
    }

    /**
     * Etiqueta legible del estado almacenado.
     */
    public function estadoLabel(): string
    {
        return match ($this->estado) {
            self::EN_RUTA => 'EN RUTA',
            self::FINALIZADO => 'FINALIZADO',
            default => (string) $this->estado,
        };
    }
}
