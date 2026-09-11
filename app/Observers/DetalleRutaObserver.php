<?php

namespace App\Observers;

use App\Models\HRControl\DetalleRuta;

class DetalleRutaObserver
{
    /**
     * Toda hoja de ruta nace "EN RUTA" salvo que se indique un estado explícito.
     */
    public function creating(DetalleRuta $detalleRuta): void
    {
        if (blank($detalleRuta->estado)) {
            $detalleRuta->estado = DetalleRuta::EN_RUTA;
        }
    }

    /**
     * Al registrarse un nuevo orden para la misma ruta, los órdenes previos que
     * seguían "EN RUTA" quedan "FINALIZADO".
     */
    public function created(DetalleRuta $detalleRuta): void
    {
        DetalleRuta::query()
            ->where('ruta_idruta', $detalleRuta->ruta_idruta)
            ->whereKeyNot($detalleRuta->getKey())
            ->where('estado', DetalleRuta::EN_RUTA)
            ->update(['estado' => DetalleRuta::FINALIZADO]);
    }
}
