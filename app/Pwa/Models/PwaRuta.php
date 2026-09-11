<?php

namespace App\Pwa\Models;

use App\Models\HRControl\Ruta;
use App\Models\WialonSTK\WialonConductor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ruta_idruta
 * @property int $wialon_conductor_id
 * @property string|null $idempotency_key
 */
class PwaRuta extends Model
{
    protected $table = 'pwa_rutas';

    protected $fillable = [
        'ruta_idruta',
        'wialon_conductor_id',
        'idempotency_key',
    ];

    /**
     * @return BelongsTo<Ruta, $this>
     */
    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class, 'ruta_idruta', 'idruta');
    }

    /**
     * @return BelongsTo<WialonConductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(WialonConductor::class, 'wialon_conductor_id');
    }
}
