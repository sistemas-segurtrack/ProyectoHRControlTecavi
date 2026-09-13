<?php

namespace App\Http\Requests\Modulos;

use Illuminate\Foundation\Http\FormRequest;

class AutenticarRutasRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }
}
