<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se dispara cuando una hoja de ruta pasa a `Ruta::FINALIZADA` (un documento
 * con `tipodocumento.condicionaFin = 1` la cerró).
 */
class HojaRutaFinalizada
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public string $idruta) {}
}
