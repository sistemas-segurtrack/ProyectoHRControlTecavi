<?php

namespace App\Pwa\Resources;

use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\DocRuta;
use App\Models\HRControl\Ruta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ruta
 */
class RutaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'idruta' => $this->idruta,
            'placa' => $this->placa,
            'piloto' => $this->piloto,
            'copiloto' => $this->copiloto,
            'precintos' => $this->precintos,
            'carreta' => $this->carreta,
            'estado' => $this->estado,
            'ordenes' => $this->whenLoaded('detalles', fn () => $this->detalles
                ->map(fn (DetalleRuta $d): array => [
                    'id' => $d->iddetalleRuta,
                    'orden' => $d->orden,
                    'geocerca' => $d->geocerca,
                    'coordenada' => $d->coordenada,
                    'kilometraje' => $d->kilometraje,
                    'observacion' => $d->observacion,
                    'estado' => $d->estado,
                    'estado_label' => $d->estadoLabel(),
                    'fh_registro' => $d->fhRegistro?->toIso8601String(),
                    'documentos' => $d->relationLoaded('documentos')
                        ? $d->documentos->map(fn (DocRuta $doc): array => [
                            'id' => $doc->iddocRuta,
                            'tipo_documento' => $doc->tipoDocumento?->nombre,
                            'documento' => $doc->documento,
                            'cantidad' => $doc->cantidad,
                            'producto' => $doc->producto,
                            'envase' => $doc->envase,
                            'peso_neto' => $doc->pesoNeto,
                            'peso_bruto' => $doc->pesoBruto,
                            'imagen' => $doc->imagen,
                        ])->values()->all()
                        : [],
                ])
                ->values()
                ->all()),
        ];
    }
}
