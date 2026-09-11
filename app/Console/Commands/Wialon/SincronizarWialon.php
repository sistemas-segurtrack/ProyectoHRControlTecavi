<?php

namespace App\Console\Commands\Wialon;

use App\Models\WialonSTK\WialonCarreta;
use App\Models\WialonSTK\WialonConductor;
use App\Models\WialonSTK\WialonGeocerca;
use App\Models\WialonSTK\WialonUnidad;
use App\Services\Wialon\WialonService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Trae de Wialon los conductores, unidades (placas), carretas y geocercas y
 * actualiza la copia local que alimenta la PWA Conductor. Programado cada hora.
 */
class SincronizarWialon extends Command
{
    protected $signature = 'wialon:sync {--solo= : conductores|unidades|carretas|geocercas}';

    protected $description = 'Sincroniza catálogos de Wialon (Segurtrack)';

    public function handle(WialonService $wialon): int
    {
        $solo = $this->option('solo');
        $ahora = now();

        try {
            if (! $solo || $solo === 'conductores') {
                $this->components->info('Conductores: '.$this->syncConductores($wialon, $ahora));
            }

            if (! $solo || $solo === 'unidades') {
                $this->components->info('Unidades/placas: '.$this->syncUnidades($wialon, $ahora));
            }

            if (! $solo || $solo === 'carretas') {
                $this->components->info('Carretas: '.$this->syncCarretas($wialon, $ahora));
            }

            if (! $solo || $solo === 'geocercas') {
                $this->components->info('Geocercas: '.$this->syncGeocercas($wialon, $ahora));
            }
        } catch (Throwable $e) {
            $this->components->error("Wialon sync falló: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function syncConductores(WialonService $wialon, \DateTimeInterface $ahora): int
    {
        $vistos = [];

        foreach ($wialon->conductores() as $c) {
            $wialonId = (int) $c['wialon_conductor_id'];
            $pwd = is_string($c['pwd'] ?? null) && $c['pwd'] !== '' ? $c['pwd'] : null;

            $fila = WialonConductor::firstOrNew(['wialon_conductor_id' => $wialonId]);

            $fila->fill([
                'codigo' => $c['codigo'],
                'dni' => $c['dni'],
                'licencia' => $c['licencia'],
                'nombre' => $c['nombre'],
                'telefono' => $c['telefono'],
                'descripcion' => $c['descripcion'],
                'activo' => true,
                'synced_at' => $ahora,
            ]);

            $sha = $pwd !== null ? hash('sha256', $pwd) : null;
            if ($sha !== $fila->pwd_sha) {
                $fila->pwd_sha = $sha;
                $fila->pwd_hash = $pwd !== null ? Hash::make($pwd) : null;
            }

            $fila->save();
            $vistos[] = $wialonId;
        }

        // Los que ya no vienen de Wialon quedan inactivos (no se borran).
        WialonConductor::query()->whereNotIn('wialon_conductor_id', $vistos ?: [0])->update(['activo' => false]);

        return count($vistos);
    }

    private function syncUnidades(WialonService $wialon, \DateTimeInterface $ahora): int
    {
        $n = 0;

        foreach ($wialon->unidades() as $u) {
            WialonUnidad::updateOrCreate(
                ['wialon_unidad_id' => $u['wialon_unidad_id']],
                [
                    'placa' => $u['placa'],
                    'nombre' => $u['nombre'],
                    'contador_kilometraje_km' => $u['contador_kilometraje_km'],
                    'synced_at' => $ahora,
                ],
            );
            $n++;
        }

        return $n;
    }

    private function syncCarretas(WialonService $wialon, \DateTimeInterface $ahora): int
    {
        $n = 0;

        foreach ($wialon->carretas() as $t) {
            WialonCarreta::updateOrCreate(
                ['recurso' => $t['recurso'], 'wialon_carreta_id' => $t['wialon_carreta_id']],
                ['nombre' => $t['nombre'], 'synced_at' => $ahora],
            );
            $n++;
        }

        return $n;
    }

    private function syncGeocercas(WialonService $wialon, \DateTimeInterface $ahora): int
    {
        $n = 0;

        foreach ($wialon->geocercas() as $g) {
            WialonGeocerca::updateOrCreate(
                ['recurso' => $g['recurso'], 'wialon_geocerca_id' => $g['wialon_geocerca_id']],
                ['nombre' => $g['nombre'], 'synced_at' => $ahora],
            );
            $n++;
        }

        return $n;
    }
}
