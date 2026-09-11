<?php

namespace App\Http\Requests\Modulos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RutaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'placa' => ['required', 'string', 'max:50'],
            'piloto' => ['required', 'string', 'max:100'],
            'copiloto' => ['nullable', 'string', 'max:100'],
            'precintos' => ['nullable', 'string', 'max:50'],
            'carreta' => ['nullable', 'string', 'max:45'],
            'estado' => ['nullable', 'string', 'max:2'],
        ];
    }
}
