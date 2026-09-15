<?php

namespace App\Support\HojasRuta;

/**
 * Arma el texto plano de WhatsApp a partir del mismo {@see ResumenHojaRuta}
 * que ya arma `ConstruirResumenHojaRuta` para el correo, con las plantillas
 * pedidas por el usuario: la parada que ABRE la hoja avisa el inicio del
 * tramo; la que lo CIERRA (tramo cerrado o fin de la hoja) avisa su fin.
 *
 * La hora es la de marcación del sistema (`fhIndicado`, cuando el servidor
 * recibió la parada), no la que escribió el conductor. Sin emojis.
 */
class MensajeWhatsappHojaRuta
{
    public static function creada(ResumenHojaRuta $r): string
    {
        return self::cuerpo('SE ESTA INICIANDO TRAMO', $r);
    }

    public static function tramoCerrado(ResumenHojaRuta $r): string
    {
        return self::cuerpo('SE HA FINALIZADO EL TRAMO', $r);
    }

    /**
     * La parada que finaliza la hoja también cierra su tramo: misma plantilla.
     */
    public static function finalizada(ResumenHojaRuta $r): string
    {
        return self::tramoCerrado($r);
    }

    private static function cuerpo(string $accion, ResumenHojaRuta $r): string
    {
        $geocerca = $r->geocerca ?? '-';
        $hora = $r->horaSistema?->format('d/m/Y H:i') ?? '-';

        return "{$accion} EN {$geocerca} CON HOJA DE RUTA: {$r->idruta} a las: {$hora}";
    }
}
