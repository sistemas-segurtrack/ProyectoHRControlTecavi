<?php

namespace App\Models\HRControl;

use Database\Factories\HRControl\ContactoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $idcontacto
 * @property string|null $geocerca
 * @property string|null $nombre
 * @property list<string>|null $correo
 * @property list<string>|null $telefonos
 */
class Contacto extends Model
{
    /** @use HasFactory<ContactoFactory> */
    use HasFactory;

    protected $table = 'contacto';

    protected $primaryKey = 'idcontacto';

    public $timestamps = false;

    protected $fillable = [
        'geocerca',
        'nombre',
        'correo',
        'telefonos',
    ];

    /**
     * `correo` y `telefonos` guardan un array JSON (varios valores por contacto).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correo' => 'array',
            'telefonos' => 'array',
        ];
    }

    /**
     * @return HasMany<DetalleRuta, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleRuta::class, 'contacto_idcontacto', 'idcontacto');
    }
}
