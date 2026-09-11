<?php

namespace App\Models\WialonSTK;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wialon_carreta_id
 * @property string|null $recurso
 * @property string $nombre
 * @property Carbon|null $synced_at
 */
class WialonCarreta extends Model
{
    protected $table = 'wialon_carretas';

    protected $fillable = [
        'wialon_carreta_id',
        'recurso',
        'nombre',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }
}
