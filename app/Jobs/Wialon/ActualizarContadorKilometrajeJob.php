<?php

namespace App\Jobs\Wialon;

use App\Models\WialonSTK\WialonUnidad;
use App\Services\Wialon\WialonService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Empuja a Wialon el kilometraje que un conductor registró en la PWA cuando
 * supera al contador que Wialon tenía (`unit/update_mileage_counter`) — ver
 * `App\Pwa\Controllers\RutaController`. Va en cola: no tiene sentido que el
 * conductor espere a que responda la API de Wialon para guardar su avance.
 */
class ActualizarContadorKilometrajeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $wialonUnidadId,
        public readonly int $kilometraje,
    ) {}

    public function handle(WialonService $wialon): void
    {
        $wialon->actualizarContadorKilometraje($this->wialonUnidadId, $this->kilometraje);

        // Se refleja al toque en la copia local — sin esperar el próximo
        // wialon:sync (cada minuto) para que la siguiente validación ya vea
        // el valor nuevo.
        WialonUnidad::query()
            ->where('wialon_unidad_id', $this->wialonUnidadId)
            ->update([
                'contador_kilometraje_km' => $this->kilometraje,
                'synced_at' => now(),
            ]);
    }
}
