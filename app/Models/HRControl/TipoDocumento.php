<?php

namespace App\Models\HRControl;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $idtipoDocumento
 * @property string|null $nombre
 * @property string|null $condicionaFin
 */
class TipoDocumento extends Model
{
    protected $table = 'tipodocumento';

    protected $primaryKey = 'idtipoDocumento';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'condicionaFin',
    ];

    /**
     * @return HasMany<DocRuta, $this>
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(DocRuta::class, 'tipoDocumento_idtipoDocumento', 'idtipoDocumento');
    }
}
