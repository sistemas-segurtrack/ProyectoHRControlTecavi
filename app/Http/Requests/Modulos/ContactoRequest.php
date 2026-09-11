<?php

namespace App\Http\Requests\Modulos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ContactoRequest extends FormRequest
{
    /**
     * Normaliza `correo` y `telefonos` a listas sin entradas vacías.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'correo' => $this->comoLista($this->input('correo')),
            'telefonos' => $this->comoLista($this->input('telefonos')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'geocerca' => ['required', 'string', 'max:200'],
            'nombre' => ['required', 'string', 'max:100'],
            'correo' => ['array'],
            'correo.*' => ['email', 'max:150'],
            'telefonos' => ['array'],
            'telefonos.*' => ['string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'correo.*' => 'correo',
            'telefonos.*' => 'teléfono',
        ];
    }

    /**
     * @return list<string>
     */
    private function comoLista(mixed $valor): array
    {
        $items = match (true) {
            is_array($valor) => $valor,
            is_string($valor) && $valor !== '' => [$valor],
            default => [],
        };

        return array_values(array_filter(
            array_map(fn ($v): string => is_string($v) ? trim($v) : '', $items),
            fn (string $v): bool => $v !== '',
        ));
    }
}
