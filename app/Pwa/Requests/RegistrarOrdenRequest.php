<?php

namespace App\Pwa\Requests;

use App\Models\HRControl\Ruta;
use App\Pwa\Requests\Concerns\ConDocumentoAdjunto;
use App\Pwa\Requests\Concerns\ValidaDocumentoAdjunto;
use App\Pwa\Requests\Concerns\ValidaKilometraje;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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

            $this->validarKilometrajeMayorQueAnterior($validator, $this->kilometrajeAnterior($ruta));
        });
    }

    /**
     * Kilometraje de la última parada registrada en la hoja, sin importar el
     * tramo (el odómetro es uno solo). `null` si no hay paradas o la última
     * no tiene kilometraje (registros anteriores a que fuera obligatorio).
     */
    private function kilometrajeAnterior(Ruta $ruta): ?int
    {
        $kilometraje = $ruta->detalles()->orderByDesc('orden')->value('kilometraje');

        return is_numeric($kilometraje) ? (int) $kilometraje : null;
    }
}
