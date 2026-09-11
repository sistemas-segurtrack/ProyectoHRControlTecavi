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
            'geocerca' => ['nullable', 'string', 'max:200'],
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

            /** @var int|null $minimoRuta */
            $minimoRuta = $ruta->detalles()
                ->whereNotNull('kilometraje')
                ->pluck('kilometraje')
                ->map(fn (string $km): int => (int) $km)
                ->max();

            $this->validarKilometrajeNoRetrocede($validator, $ruta->placa, $minimoRuta);
        });
    }
}
