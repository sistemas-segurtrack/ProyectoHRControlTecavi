<?php

namespace App\Pwa\Requests;

use App\Models\HRControl\Ruta;
use App\Pwa\Requests\Concerns\ConDocumentoAdjunto;
use App\Pwa\Requests\Concerns\ValidaDocumentoAdjunto;
use App\Pwa\Requests\Concerns\ValidaKilometraje;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class RegistrarOrdenRequest extends FormRequest implements ConDocumentoAdjunto
{
    use ValidaDocumentoAdjunto;
    use ValidaKilometraje;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contacto_idcontacto' => ['nullable', 'integer', 'exists:contacto,idcontacto'],
            'geocerca' => ['required', 'string', 'max:200'],
            'coordenada' => ['nullable', 'string', 'max:50'],
            'fhRegistro' => ['nullable', 'date'],
            'kilometraje' => $this->reglasKilometraje(),
            'observacion' => ['nullable', 'string', 'max:500'],
            ...$this->reglasDocumento(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ruta = Ruta::query()->where('idruta', (string) $this->route('ruta'))->first();
            if ($ruta === null) {
                return;
            }

            $this->validarKilometrajeMayorQueAnterior($validator, $ruta->kilometrajeUltimaParada());
        });
    }

    /**
     * Revalida el kilometraje ya con la hoja bloqueada (`RutaController::orden()`):
     * entre la validación de arriba y el guardado pudo registrarse otra parada
     * de la misma hoja (el mismo conductor sincronizando desde otro celular).
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
}
