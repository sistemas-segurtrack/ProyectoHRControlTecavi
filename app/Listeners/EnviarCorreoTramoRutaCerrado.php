<?php

namespace App\Listeners;

use App\Events\TramoRutaCerrado;
use App\Mail\HojaRutaTramoCerradoMail;
use App\Support\HojasRuta\ConstruirResumenHojaRuta;
use App\Support\HojasRuta\CorreosDeGeocerca;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class EnviarCorreoTramoRutaCerrado implements ShouldQueue
{
    public string $queue = 'default';

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private readonly ConstruirResumenHojaRuta $constructor) {}

    public function handle(TramoRutaCerrado $event): void
    {
        $resumen = $this->constructor->paraTramoCerrado($event->idruta);

        if ($resumen === null) {
            return;
        }

        $correos = CorreosDeGeocerca::para($resumen->geocerca);

        if ($correos === []) {
            return;
        }

        Mail::to($correos)->send(new HojaRutaTramoCerradoMail($resumen));
    }
}
