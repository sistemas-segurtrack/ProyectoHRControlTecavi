<?php

namespace App\Support\HojasRuta;

use App\Models\HRControl\Contacto;

/**
 * Resuelve a quién avisar por correo: los `correo` de los `Contacto` cuya
 * `geocerca` coincide con la de la hoja de ruta. Sin geocerca o sin contacto
 * que coincida, no hay a quién avisar.
 */
class CorreosDeGeocerca
{
    /**
     * @return list<string>
     */
    public static function para(?string $geocerca): array
    {
        $geocerca = trim((string) $geocerca);

        if ($geocerca === '') {
            return [];
        }

        /** @var list<string> $correos */
        $correos = Contacto::query()
            ->whereRaw('TRIM(geocerca) = ?', [$geocerca])
            ->get(['correo'])
            ->flatMap(fn (Contacto $contacto) => $contacto->correo ?? [])
            ->map(fn ($correo) => trim((string) $correo))
            ->filter(fn (string $correo) => filter_var($correo, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();

        return $correos;
    }
}
