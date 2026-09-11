<?php

namespace App\Pwa\Resources;

use App\Models\WialonSTK\WialonConductor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WialonConductor
 */
class ConductorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wialon_conductor_id' => $this->wialon_conductor_id,
            'codigo' => $this->codigo,
            'dni' => $this->dni,
            'nombre' => $this->nombre,
            'licencia' => $this->licencia,
            'telefono' => $this->telefono,
            'descripcion' => $this->descripcion,
        ];
    }
}
