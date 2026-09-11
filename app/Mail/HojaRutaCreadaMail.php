<?php

namespace App\Mail;

use App\Support\HojasRuta\ResumenHojaRuta;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class HojaRutaCreadaMail extends Mailable
{
    public function __construct(public ResumenHojaRuta $resumen) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Hoja de ruta {$this->resumen->idruta} creada",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.hojas-ruta.creada',
            with: ['r' => $this->resumen],
        );
    }
}
