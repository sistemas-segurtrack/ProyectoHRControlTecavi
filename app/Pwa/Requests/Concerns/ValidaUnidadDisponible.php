<?php

namespace App\Pwa\Requests\Concerns;

use App\Models\HRControl\Ruta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * "Nueva Ruta" no puede iniciar una hoja sobre una unidad que ya tiene un
 * tramo abierto (EN RUTA) — sin importar qué conductor lo abrió: el chofer
 * elige la placa y el sistema debe detectar si esa unidad ya está en curso
 * (hay que continuarla o finalizarla) o si de verdad está libre para
 * empezar una hoja desde cero.
 *
 * @mixin FormRequest
 */
trait ValidaUnidadDisponible
{
    protected function validarUnidadDisponible(Validator $validator, ?string $placa): void
    {
        if ($placa === null || $placa === '') {
            return;
        }

        $idruta = Ruta::idEnRutaPorPlaca($placa);
        if ($idruta === null) {
            return;
        }

        $validator->errors()->add(
            'placa',
            "La unidad {$placa} ya tiene una hoja de ruta en curso ({$idruta}). Continúala o finalízala antes de iniciar una nueva.",
        );
    }
}
