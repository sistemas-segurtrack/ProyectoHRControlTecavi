<?php

namespace App\Pwa\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Reglas y accesores del documento adjunto opcional, compartidos por
 * "Nueva Ruta" (primer orden) y "Continuar" (siguientes órdenes).
 *
 * @mixin FormRequest
 */
trait ValidaDocumentoAdjunto
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function reglasDocumento(): array
    {
        return [
            'adjuntar' => ['nullable', 'boolean'],
            'documento' => ['nullable', 'array'],
            'documento.tipo_documento_id' => ['required_if:adjuntar,true,1', 'nullable', 'integer', 'exists:tipodocumento,idtipoDocumento'],
            // Código del documento y foto son obligatorios en cuanto se activa "Adjuntar".
            'documento.documento' => ['required_if:adjuntar,true,1', 'nullable', 'string', 'max:50'],
            'documento.producto' => ['nullable', 'string', 'max:50'],
            // Campos numéricos (opcionales).
            'documento.cantidad' => ['nullable', 'numeric'],
            'documento.envase' => ['nullable', 'numeric'],
            'documento.peso_neto' => ['nullable', 'numeric'],
            'documento.peso_bruto' => ['nullable', 'numeric'],
            // Foto del documento tomada con la cámara (o imagen de la galería).
            'documento.imagen' => ['required_if:adjuntar,true,1', 'nullable', 'file', 'max:307200', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function adjunta(): bool
    {
        return $this->boolean('adjuntar') && $this->filled('documento.tipo_documento_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDocumento(): array
    {
        /** @var array<string, mixed> $doc */
        $doc = (array) $this->validated('documento', []);

        return $doc;
    }

    public function archivoDocumento(): ?UploadedFile
    {
        $archivo = $this->file('documento.imagen');

        return $archivo instanceof UploadedFile ? $archivo : null;
    }
}
