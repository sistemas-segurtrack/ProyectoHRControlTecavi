<?php

namespace App\Support\HojasRuta;

use App\Models\HRControl\Contacto;

/**
 * Resuelve a quién avisar por WhatsApp: los `telefonos` de los `Contacto`
 * cuya `geocerca` coincide con la de la hoja de ruta. Mismo criterio que
 * {@see CorreosDeGeocerca}, pero con el número ya en el formato que pide la
 * API de WhatsApp (`51` + 9 dígitos) -- `contacto.telefonos` se guarda SOLO
 * en formato local (`ContactoRequest`), sin el código de país.
 */
class TelefonosDeGeocerca
{
    private const CODIGO_PAIS = '51';

    /**
     * @return list<string>
     */
    public static function para(?string $geocerca): array
    {
        $geocerca = trim((string) $geocerca);

        if ($geocerca === '') {
            return [];
        }

        /** @var list<string> $telefonos */
        $telefonos = Contacto::query()
            ->whereRaw('TRIM(geocerca) = ?', [$geocerca])
            ->get(['telefonos'])
            ->flatMap(fn (Contacto $contacto) => $contacto->telefonos ?? [])
            ->map(fn ($telefono) => self::CODIGO_PAIS.preg_replace('/\D+/', '', (string) $telefono))
            ->filter(fn (string $telefono) => preg_match('/^51\d{9}$/', $telefono) === 1)
            ->unique()
            ->values()
            ->all();

        return $telefonos;
    }
}
