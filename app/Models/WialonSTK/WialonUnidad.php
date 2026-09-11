<?php

namespace App\Models\WialonSTK;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $wialon_unidad_id
 * @property string|null $placa
 * @property string|null $nombre
 * @property int|null $contador_kilometraje_km
 * @property Carbon|null $synced_at
 */
class WialonUnidad extends Model
{
    protected $table = 'wialon_unidades';

    protected $primaryKey = 'wialon_unidad_id';

    protected $keyType = 'int';

    public $incrementing = false;

    protected $fillable = [
        'wialon_unidad_id',
        'placa',
        'nombre',
        'contador_kilometraje_km',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contador_kilometraje_km' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
