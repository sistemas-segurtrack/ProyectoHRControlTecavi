<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente del servicio interno de WhatsApp de Segurtrack. Sin autenticación
 * propia (ni token ni API key) y siempre responde HTTP 200 -- el éxito real
 * va en el body (`ok`), nunca en el status code.
 *
 * Confirmado con `curl` real contra el endpoint (2026-09-13):
 * - Error de validación: `{"ok":false,"error":{"code":"VALIDATION_FAILED","fields":{...}}}`.
 * - Otro error (p. ej. el gateway de WhatsApp caído): `{"ok":false,"error":{"code":"...","message":"..."}}`.
 * - Éxito real: `{"ok":true,"data":{"ok":true,"resultados":[{"ok":true,"status":200,"phone":"...","resp":{"code":"SUCCESS",...}},...]}}`
 *   — `resultados` trae un resultado por número, así que un envío a varios
 *   destinatarios puede tener éxitos parciales.
 */
class WhatsappService
{
    public function __construct(
        private readonly string $url,
        private readonly bool $habilitado,
    ) {}

    /**
     * Manda el mismo mensaje a una o varias placas en formato `51XXXXXXXXX`
     * (ver `TelefonosDeGeocerca::para()`, que ya deja los números así).
     * Deshabilitado o sin destinatarios: no hace ninguna petición.
     *
     * @param  list<string>  $telefonos
     */
    public function enviar(array $telefonos, string $mensaje): bool
    {
        if (! $this->habilitado || $telefonos === []) {
            return false;
        }

        try {
            $respuesta = Http::asJson()->acceptJson()->post($this->url, [
                'TipoEnvio' => 'numeros',
                'Destinatarios' => $telefonos,
                'Contenido' => $mensaje,
            ]);
        } catch (\Throwable $e) {
            Log::warning('whatsapp.enviar: excepción de red', [
                'telefonos' => $telefonos,
                'mensaje' => $e->getMessage(),
            ]);

            return false;
        }

        if ($respuesta->json('ok') === true) {
            return true;
        }

        Log::warning('whatsapp.enviar: la API respondió sin éxito', [
            'telefonos' => $telefonos,
            'status_http' => $respuesta->status(),
            'codigo' => $respuesta->json('error.code'),
            'mensaje' => $respuesta->json('error.message'),
        ]);

        return false;
    }
}
