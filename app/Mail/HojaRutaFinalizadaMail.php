<?php

namespace App\Mail;

use App\Support\HojasRuta\ResumenHojaRuta;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class HojaRutaFinalizadaMail extends Mailable
{
    public function __construct(public ResumenHojaRuta $resumen) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Hoja de ruta {$this->resumen->idruta} finalizada",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.hojas-ruta.finalizada',
            with: ['r' => $this->resumen],
        );
    }
}
