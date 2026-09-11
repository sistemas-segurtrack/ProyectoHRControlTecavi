<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se dispara cuando se crea una hoja de ruta (primer orden registrado).
 */
class HojaRutaCreada
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public string $idruta) {}
}
