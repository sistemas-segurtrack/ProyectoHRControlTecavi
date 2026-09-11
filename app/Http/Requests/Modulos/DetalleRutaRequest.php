<?php

namespace App\Http\Requests\Modulos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DetalleRutaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contacto_idcontacto' => ['required', 'integer', 'exists:contacto,idcontacto'],
            'geocerca' => ['nullable', 'string', 'max:200'],
            'coordenada' => ['nullable', 'string', 'max:50'],
            'kilometraje' => ['nullable', 'string', 'max:10'],
            'fhRegistro' => ['nullable', 'date'],
            'fhIndicado' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }
}
