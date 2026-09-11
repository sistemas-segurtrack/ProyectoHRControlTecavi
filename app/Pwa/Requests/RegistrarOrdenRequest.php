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

            $this->validarKilometrajeNoRetrocede($validator, $ruta->placa, $this->minimoDelTramo($ruta));
        });
    }

    /**
     * Cada tramo de la hoja de ruta es independiente (parada impar = inicio,
     * parada par = fin): el kilometraje de un tramo ya cerrado no debe
     * condicionar el del tramo siguiente. Solo dentro del MISMO tramo el
     * "fin" no puede ser menor que su propio "inicio" — por eso, si este
     * nuevo orden cierra el tramo abierto, el mínimo es el kilometraje de
     * esa misma parada de inicio; si en cambio abre un tramo nuevo, no hay
     * mínimo de la ruta (solo sigue aplicando el contador de Wialon).
     */
    private function minimoDelTramo(Ruta $ruta): ?int
    {
        $ultimoDetalle = $ruta->detalles()->orderByDesc('orden')->first();
        if ($ultimoDetalle === null) {
            return null;
        }

        $siguienteOrden = $ultimoDetalle->orden + 1;
        $cierraTramo = $siguienteOrden % 2 === 0;
        if (! $cierraTramo) {
            return null;
        }

        return $ultimoDetalle->kilometraje !== null ? (int) $ultimoDetalle->kilometraje : null;
    }
}
