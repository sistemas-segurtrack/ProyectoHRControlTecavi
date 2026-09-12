<?php

namespace App\Pwa\Requests;

use App\Pwa\Requests\Concerns\ConDocumentoAdjunto;
use App\Pwa\Requests\Concerns\ValidaDocumentoAdjunto;
use App\Pwa\Requests\Concerns\ValidaKilometraje;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CrearRutaRequest extends FormRequest implements ConDocumentoAdjunto
{
    use ValidaDocumentoAdjunto;
    use ValidaKilometraje;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'placa' => ['required', 'string', 'max:50'],
            'copiloto' => ['nullable', 'string', 'max:100'],
            'precintos' => ['nullable', 'string', 'max:50'],
            'carreta' => ['nullable', 'string', 'max:45'],
            // Al iniciar una hoja de ruta no se compara contra el contador de
            // Wialon: puede estar desactualizado (se sincroniza cada minuto) y
            // rechazaba de arranque kilometrajes reales válidos. Solo se
            // valida "no retrocede" dentro de la propia ruta, en `Continuar`.
            'kilometraje' => $this->reglasKilometraje(),
            'geocerca' => ['nullable', 'string', 'max:200'],
            'coordenada' => ['nullable', 'string', 'max:50'],
            'fhRegistro' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
            ...$this->reglasDocumento(),
        ];
    }
}
