<?php

namespace App\Support\HojasRuta;

/**
 * Arma el texto plano de WhatsApp a partir del mismo {@see ResumenHojaRuta}
 * que ya arma `ConstruirResumenHojaRuta` para el correo. `*texto*` es la
 * sintaxis de negrita de WhatsApp (no Markdown `**`). Sin emojis (a pedido
 * del usuario).
 */
class MensajeWhatsappHojaRuta
{
    public static function creada(ResumenHojaRuta $r): string
    {
        return self::cuerpo('creada', $r);
    }

    public static function tramoCerrado(ResumenHojaRuta $r): string
    {
        return self::cuerpo('tramo cerrado', $r);
    }

    public static function finalizada(ResumenHojaRuta $r): string
    {
        return self::cuerpo('finalizada', $r);
    }

    private static function cuerpo(string $estado, ResumenHojaRuta $r): string
    {
        $lineas = [
            "*Hoja de ruta {$r->idruta}* {$estado}",
            'Conductor: '.($r->piloto ?? '-'),
            'Placa: '.($r->placa ?? '-'),
            'Geocerca: '.($r->geocerca ?? '-'),
            'Fecha: '.($r->fecha?->format('d/m/Y H:i') ?? '-'),
        ];

        return implode("\n", $lineas);
    }
}
