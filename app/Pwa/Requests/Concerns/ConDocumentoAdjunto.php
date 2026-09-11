<?php

namespace App\Pwa\Requests\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Contrato de un FormRequest que acepta un documento adjunto opcional
 * (switch "Adjuntar" del formulario de la PWA Conductor).
 */
interface ConDocumentoAdjunto
{
    /**
     * Hay un documento adjunto válido (switch activo + tipo de documento elegido).
     */
    public function adjunta(): bool;

    /**
     * Campos de texto del documento ya validados.
     *
     * @return array<string, mixed>
     */
    public function datosDocumento(): array;

    /**
     * Archivo subido con el documento (pdf / imagen / excel), si lo hay.
     */
    public function archivoDocumento(): ?UploadedFile;
}
