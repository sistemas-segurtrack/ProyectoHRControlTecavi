<?php

namespace App\Pwa\Requests\Concerns;

use App\Models\WialonSTK\WialonUnidad;
use App\Services\Wialon\WialonService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Regla compartida por "Nueva Ruta" y "Continuar": el kilometraje no puede
 * ser menor al último conocido para la unidad — ni el contador de Wialon
 * (`wialon_unidades.contador_kilometraje_km`, sincronizado cada minuto) ni,
 * en "Continuar", el último kilometraje ya registrado en esta misma hoja de
 * ruta.
 *
 * @mixin FormRequest
 */
trait ValidaKilometraje
{
    /**
     * @return array<int, ValidationRule|string>
     */
    protected function reglasKilometraje(): array
    {
        return ['nullable', 'integer', 'min:0', 'max:'.WialonService::CONTADOR_KM_MAX];
    }

    /**
     * @param  string|null  $placa  placa de la unidad (para consultar el contador de Wialon)
     * @param  int|null  $minimoRuta  último kilometraje ya registrado en esta misma hoja de ruta
     */
    protected function validarKilometrajeNoRetrocede(
        Validator $validator,
        ?string $placa,
        ?int $minimoRuta = null,
    ): void {
        $kilometraje = $this->input('kilometraje');
        if ($kilometraje === null || $kilometraje === '') {
            return;
        }

        $km = (int) $kilometraje;
        $referencia = $minimoRuta ?? 0;

        if ($placa !== null) {
            $kmWialon = WialonUnidad::query()->where('placa', $placa)->value('contador_kilometraje_km');
            if ($kmWialon !== null) {
                $referencia = max($referencia, (int) $kmWialon);
            }
        }

        if ($km < $referencia) {
            $validator->errors()->add(
                'kilometraje',
                "El kilometraje no puede ser menor al último registrado ({$referencia} km).",
            );
        }
    }
}
