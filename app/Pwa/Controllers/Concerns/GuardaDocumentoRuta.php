<?php

namespace App\Pwa\Controllers\Concerns;

use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\DocRuta;
use App\Models\HRControl\TipoDocumento;
use App\Pwa\Requests\Concerns\ConDocumentoAdjunto;
use Illuminate\Support\Facades\Storage;

/**
 * Registra el documento adjunto (docruta) de un orden de la hoja de ruta y
 * resuelve si ese documento la finaliza (`tipodocumento.condicionaFin = 1`).
 */
trait GuardaDocumentoRuta
{
    /**
     * Tipo de documento adjunto en la petición, o `null` si no se adjunta.
     */
    protected function tipoDocumentoAdjunto(ConDocumentoAdjunto $request): ?TipoDocumento
    {
        if (! $request->adjunta()) {
            return null;
        }

        /** @var TipoDocumento $tipo */
        $tipo = TipoDocumento::query()->findOrFail(
            (int) ($request->datosDocumento()['tipo_documento_id'] ?? 0),
        );

        return $tipo;
    }

    /**
     * ¿El documento cierra la hoja de ruta al registrarse?
     */
    protected function documentoFinaliza(?TipoDocumento $tipo): bool
    {
        return $tipo !== null && (string) $tipo->condicionaFin === '1';
    }

    /**
     * Crea el `DocRuta` del orden, subiendo el archivo a `storage/app/public/docruta`.
     */
    protected function guardarDocumento(DetalleRuta $orden, TipoDocumento $tipo, ConDocumentoAdjunto $request): void
    {
        $doc = $request->datosDocumento();
        $archivo = $request->archivoDocumento();

        $urlImagen = null;
        if ($archivo !== null) {
            $ruta = $archivo->store('docruta', 'public');
            $urlImagen = is_string($ruta) ? Storage::disk('public')->url($ruta) : null;
        }

        DocRuta::query()->create([
            'detalleRuta_iddetalleRuta' => $orden->iddetalleRuta,
            'tipoDocumento_idtipoDocumento' => $tipo->idtipoDocumento,
            'documento' => $doc['documento'] ?? null,
            'cantidad' => $doc['cantidad'] ?? null,
            'producto' => $doc['producto'] ?? null,
            'envase' => $doc['envase'] ?? null,
            'pesoNeto' => $doc['peso_neto'] ?? null,
            'pesoBruto' => $doc['peso_bruto'] ?? null,
            'imagen' => $urlImagen,
        ]);
    }
}
