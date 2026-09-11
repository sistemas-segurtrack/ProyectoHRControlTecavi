<?php

namespace App\Listeners;

use App\Events\HojaRutaFinalizada;
use App\Mail\HojaRutaFinalizadaMail;
use App\Support\HojasRuta\ConstruirResumenHojaRuta;
use App\Support\HojasRuta\CorreosDeGeocerca;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class EnviarCorreoHojaRutaFinalizada implements ShouldQueue
{
    public string $queue = 'default';

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private readonly ConstruirResumenHojaRuta $constructor) {}

    public function handle(HojaRutaFinalizada $event): void
    {
        $resumen = $this->constructor->paraFinalizacion($event->idruta);

        if ($resumen === null) {
            return;
        }

        $correos = CorreosDeGeocerca::para($resumen->geocerca);

        if ($correos === []) {
            return;
        }

        Mail::to($correos)->send(new HojaRutaFinalizadaMail($resumen));
    }
}
