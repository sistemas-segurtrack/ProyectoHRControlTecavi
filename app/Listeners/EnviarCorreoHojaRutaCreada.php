<?php

namespace App\Listeners;

use App\Events\HojaRutaCreada;
use App\Mail\HojaRutaCreadaMail;
use App\Support\HojasRuta\ConstruirResumenHojaRuta;
use App\Support\HojasRuta\CorreosDeGeocerca;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class EnviarCorreoHojaRutaCreada implements ShouldQueue
{
    public string $queue = 'default';

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private readonly ConstruirResumenHojaRuta $constructor) {}

    public function handle(HojaRutaCreada $event): void
    {
        $resumen = $this->constructor->paraCreacion($event->idruta);

        if ($resumen === null) {
            return;
        }

        $correos = CorreosDeGeocerca::para($resumen->geocerca);

        if ($correos === []) {
            return;
        }

        Mail::to($correos)->send(new HojaRutaCreadaMail($resumen));
    }
}
