<?php

namespace App\Models\WialonSTK;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property int $wialon_conductor_id
 * @property string|null $codigo
 * @property string|null $dni
 * @property string|null $licencia
 * @property string $nombre
 * @property string|null $telefono
 * @property string|null $descripcion
 * @property string|null $pwd_hash
 * @property bool $activo
 * @property Carbon|null $synced_at
 */
class WialonConductor extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasApiTokens;

    protected $table = 'wialon_conductores';

    protected $fillable = [
        'wialon_conductor_id',
        'codigo',
        'dni',
        'licencia',
        'nombre',
        'telefono',
        'descripcion',
        'pwd_hash',
        'pwd_sha',
        'activo',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
