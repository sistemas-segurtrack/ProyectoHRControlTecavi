<?php

namespace App\Pwa\Requests;

use App\Pwa\Requests\Concerns\ConDocumentoAdjunto;
use App\Pwa\Requests\Concerns\ValidaDocumentoAdjunto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CrearRutaRequest extends FormRequest implements ConDocumentoAdjunto
{
    use ValidaDocumentoAdjunto;

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
            'kilometraje' => ['nullable', 'string', 'max:10'],
            'geocerca' => ['nullable', 'string', 'max:200'],
            'coordenada' => ['nullable', 'string', 'max:50'],
            'observacion' => ['nullable', 'string', 'max:500'],
            ...$this->reglasDocumento(),
        ];
    }
}
