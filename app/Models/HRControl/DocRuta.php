<?php

namespace App\Models\HRControl;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $iddocRuta
 * @property int $detalleRuta_iddetalleRuta
 * @property int $tipoDocumento_idtipoDocumento
 * @property string|null $documento
 * @property string|null $cantidad
 * @property string|null $producto
 * @property string|null $envase
 * @property string|null $pesoNeto
 * @property string|null $pesoBruto
 * @property string|null $imagen
 */
class DocRuta extends Model
{
    protected $table = 'docruta';

    protected $primaryKey = 'iddocRuta';

    public $timestamps = false;

    protected $fillable = [
        'detalleRuta_iddetalleRuta',
        'tipoDocumento_idtipoDocumento',
        'documento',
        'cantidad',
        'producto',
        'envase',
        'pesoNeto',
        'pesoBruto',
        'imagen',
    ];

    /**
     * @return BelongsTo<DetalleRuta, $this>
     */
    public function detalleRuta(): BelongsTo
    {
        return $this->belongsTo(DetalleRuta::class, 'detalleRuta_iddetalleRuta', 'iddetalleRuta');
    }

    /**
     * @return BelongsTo<TipoDocumento, $this>
     */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipoDocumento_idtipoDocumento', 'idtipoDocumento');
    }
}
