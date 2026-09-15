<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se dispara cuando un avance cierra un tramo de la hoja de ruta (parada par:
 * 2, 4, 6…) sin finalizarla. Si ese mismo avance la finaliza, solo se
 * dispara {@see HojaRutaFinalizada}.
 */
class TramoRutaCerrado
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public string $idruta) {}
}
