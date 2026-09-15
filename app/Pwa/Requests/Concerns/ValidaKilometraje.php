<?php

namespace App\Pwa\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Regla compartida por "Nueva Ruta" y "Continuar": el kilometraje es
 * obligatorio y, como el odómetro nunca repite ni retrocede, el de cada
 * parada debe ser ESTRICTAMENTE mayor al de la parada anterior de la misma
 * hoja de ruta. No se compara contra el contador de Wialon: se sincroniza
 * cada minuto y puede estar desactualizado.
 *
 * @mixin FormRequest
 */
trait ValidaKilometraje
{
    /**
     * Tope del kilometraje que puede ingresar el conductor. Queda por debajo
     * del máximo del contador de Wialon (`WialonService::CONTADOR_KM_MAX`,
     * 4294967) para dejar margen: como cada parada debe superar a la
     * anterior, una parada registrada justo en ese máximo bloquearía la
     * siguiente.
     */
    public const KILOMETRAJE_MAXIMO = 4294800;

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function reglasKilometraje(): array
    {
        return ['required', 'integer', 'min:0', 'max:'.self::KILOMETRAJE_MAXIMO];
    }

    /**
     * @param  int|null  $anterior  kilometraje de la parada anterior (`null` si no hay o no lo tiene)
     */
    protected function validarKilometrajeMayorQueAnterior(Validator $validator, ?int $anterior): void
    {
        $kilometraje = $this->input('kilometraje');

        if ($anterior === null || ! is_numeric($kilometraje) || $validator->errors()->has('kilometraje')) {
            return;
        }

        if ((int) $kilometraje <= $anterior) {
            $validator->errors()->add('kilometraje', $this->mensajeKilometrajeNoMayor($anterior));
        }
    }

    /**
     * Revalida el kilometraje ya con la hoja bloqueada (`RutaController`): entre
     * la validación del request y el guardado pudo registrarse otra parada de
     * la misma hoja.
     *
     * @throws ValidationException
     */
    public function asegurarKilometrajeMayorQue(?int $anterior): void
    {
        $kilometraje = $this->validated('kilometraje');

        if ($anterior !== null && is_numeric($kilometraje) && (int) $kilometraje <= $anterior) {
            throw ValidationException::withMessages([
                'kilometraje' => $this->mensajeKilometrajeNoMayor($anterior),
            ]);
        }
    }

    protected function mensajeKilometrajeNoMayor(int $anterior): string
    {
        return "El kilometraje debe ser mayor al de la parada anterior ({$anterior} km).";
    }
}
