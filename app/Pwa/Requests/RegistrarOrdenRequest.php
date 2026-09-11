<?php

namespace App\Pwa\Requests;

use App\Pwa\Requests\Concerns\ConDocumentoAdjunto;
use App\Pwa\Requests\Concerns\ValidaDocumentoAdjunto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegistrarOrdenRequest extends FormRequest implements ConDocumentoAdjunto
{
    use ValidaDocumentoAdjunto;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contacto_idcontacto' => ['nullable', 'integer', 'exists:contacto,idcontacto'],
            'geocerca' => ['nullable', 'string', 'max:200'],
            'coordenada' => ['nullable', 'string', 'max:50'],
            'kilometraje' => ['nullable', 'string', 'max:10'],
            'observacion' => ['nullable', 'string', 'max:500'],
            ...$this->reglasDocumento(),
        ];
    }
}
