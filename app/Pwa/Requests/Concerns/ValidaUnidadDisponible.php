<?php

namespace App\Pwa\Requests\Concerns;

use App\Models\HRControl\Ruta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * "Nueva Ruta" no puede iniciar una hoja sobre una unidad que ya está EN
 * RUTA de verdad (un tramo abierto, sin cerrar) — sin importar qué
 * conductor lo abrió: el chofer elige la placa y el sistema debe detectar
 * si esa unidad ya está en ruta (hay que continuarla o finalizarla) o si de
 * verdad está libre para empezar una hoja desde cero. Una unidad con una
 * hoja ACTIVA pero ya sin ningún tramo abierto (esperando el documento que
 * la finalice) SÍ se puede elegir — ver `Ruta::idEnRutaPorPlaca()`.
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
            "La unidad {$placa} ya tiene una hoja de ruta en ruta ({$idruta}). Continúala o finalízala antes de iniciar una nueva.",
        );
    }
}
