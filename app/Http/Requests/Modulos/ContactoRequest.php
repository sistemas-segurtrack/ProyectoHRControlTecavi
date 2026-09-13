<?php

namespace App\Http\Requests\Modulos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ContactoRequest extends FormRequest
{
    /**
     * Normaliza `correo` y `telefonos` a listas sin entradas vacías.
     *
     * `telefonos` además queda SOLO en formato local (9 dígitos, sin `51`):
     * es lo que espera `TelefonosDeGeocerca` para avisar por WhatsApp cuando
     * una hoja de ruta se crea/finaliza. Un valor con el prefijo del país (11
     * dígitos empezando en 51) se recorta en vez de rechazarse; el resto de
     * caracteres no numéricos se descartan.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'correo' => $this->comoLista($this->input('correo')),
            'telefonos' => $this->comoLista($this->input('telefonos'), fn (string $v): string => $this->soloDigitosLocal($v)),
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
            'telefonos.*' => ['digits:9'],
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
     * @param  (callable(string): string)|null  $transformar  aplicado a cada valor después del trim
     * @return list<string>
     */
    private function comoLista(mixed $valor, ?callable $transformar = null): array
    {
        $items = match (true) {
            is_array($valor) => $valor,
            is_string($valor) && $valor !== '' => [$valor],
            default => [],
        };

        $items = array_map(fn ($v): string => is_string($v) ? trim($v) : '', $items);
        if ($transformar !== null) {
            $items = array_map($transformar, $items);
        }

        return array_values(array_filter($items, fn (string $v): bool => $v !== ''));
    }

    /**
     * Deja solo dígitos y, si quedan 11 empezando en el código de país de
     * Perú (51), se lo recorta — el resto de valores (con menos o más
     * dígitos) se dejan tal cual para que `digits:9` los rechace con un
     * mensaje claro, en vez de truncarlos en silencio a algo irreconocible.
     */
    private function soloDigitosLocal(string $valor): string
    {
        $digitos = preg_replace('/\D+/', '', $valor) ?? '';

        if (strlen($digitos) === 11 && str_starts_with($digitos, '51')) {
            return substr($digitos, 2);
        }

        return $digitos;
    }
}
