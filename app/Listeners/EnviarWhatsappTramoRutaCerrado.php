<?php

namespace App\Listeners;

use App\Events\TramoRutaCerrado;
use App\Services\Whatsapp\WhatsappService;
use App\Support\HojasRuta\ConstruirResumenHojaRuta;
use App\Support\HojasRuta\MensajeWhatsappHojaRuta;
use App\Support\HojasRuta\TelefonosDeGeocerca;
use Illuminate\Contracts\Queue\ShouldQueue;

class EnviarWhatsappTramoRutaCerrado implements ShouldQueue
{
    public string $queue = 'default';

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly ConstruirResumenHojaRuta $constructor,
        private readonly WhatsappService $whatsapp,
    ) {}

    public function handle(TramoRutaCerrado $event): void
    {
        $resumen = $this->constructor->paraTramoCerrado($event->idruta);

        if ($resumen === null) {
            return;
        }

        $telefonos = TelefonosDeGeocerca::para($resumen->geocerca);

        if ($telefonos === []) {
            return;
        }

        $this->whatsapp->enviar($telefonos, MensajeWhatsappHojaRuta::tramoCerrado($resumen));
    }
}
