<?php

namespace App\Console\Commands\Wialon;

use App\Models\WialonSTK\WialonCarreta;
use App\Models\WialonSTK\WialonConductor;
use App\Models\WialonSTK\WialonGeocerca;
use App\Models\WialonSTK\WialonUnidad;
use App\Services\Wialon\WialonService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Conductores: se desactivan (no se borran -- `pwa_rutas` y sus tokens
     * Sanctum los referencian) cuando Wialon deja de devolverlos, en vez de
     * desaparecer sin dejar rastro de a quién perteneció una hoja de ruta
     * vieja. Tanto un cambio de contraseña como una desactivación cierran de
     * inmediato cualquier sesión de la PWA abierta con la credencial vieja.
     */
    private function syncConductores(WialonService $wialon, \DateTimeInterface $ahora): int
    {
        $vistos = [];

        foreach ($wialon->conductores() as $c) {
            $wialonId = (int) $c['wialon_conductor_id'];
            $pwd = is_string($c['pwd'] ?? null) && $c['pwd'] !== '' ? $c['pwd'] : null;

            $fila = WialonConductor::firstOrNew(['wialon_conductor_id' => $wialonId]);
            $yaExistia = $fila->exists;

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
            $cambioClave = $yaExistia && $sha !== $fila->pwd_sha;
            if ($sha !== $fila->pwd_sha) {
                $fila->pwd_sha = $sha;
                $fila->pwd_hash = $pwd !== null ? Hash::make($pwd) : null;
            }

            $fila->save();

            if ($cambioClave) {
                // La contraseña cambió en Wialon: la sesión de la PWA que
                // seguía usando la clave vieja no debe seguir viva.
                $fila->tokens()->delete();
            }

            $vistos[] = $wialonId;
        }

        if ($vistos !== []) {
            $yaNoVienen = WialonConductor::query()
                ->whereNotIn('wialon_conductor_id', $vistos)
                ->where('activo', true)
                ->get();

            foreach ($yaNoVienen as $conductor) {
                $conductor->tokens()->delete();
            }

            WialonConductor::query()->whereNotIn('wialon_conductor_id', $vistos)->update(['activo' => false]);
        }

        return count($vistos);
    }

    private function syncUnidades(WialonService $wialon, \DateTimeInterface $ahora): int
    {
        $vistos = [];

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
            $vistos[] = $u['wialon_unidad_id'];
        }

        $this->eliminarNoVistos(WialonUnidad::query(), 'wialon_unidad_id', $vistos);

        return count($vistos);
    }

    private function syncCarretas(WialonService $wialon, \DateTimeInterface $ahora): int
    {
        $vistos = [];

        foreach ($wialon->carretas() as $t) {
            WialonCarreta::updateOrCreate(
                ['recurso' => $t['recurso'], 'wialon_carreta_id' => $t['wialon_carreta_id']],
                ['nombre' => $t['nombre'], 'synced_at' => $ahora],
            );
            $vistos[] = $t['wialon_carreta_id'];
        }

        $this->eliminarNoVistos(WialonCarreta::query(), 'wialon_carreta_id', $vistos);

        return count($vistos);
    }

    private function syncGeocercas(WialonService $wialon, \DateTimeInterface $ahora): int
    {
        $vistos = [];

        foreach ($wialon->geocercas() as $g) {
            WialonGeocerca::updateOrCreate(
                ['recurso' => $g['recurso'], 'wialon_geocerca_id' => $g['wialon_geocerca_id']],
                ['nombre' => $g['nombre'], 'synced_at' => $ahora],
            );
            $vistos[] = $g['wialon_geocerca_id'];
        }

        $this->eliminarNoVistos(WialonGeocerca::query(), 'wialon_geocerca_id', $vistos);

        return count($vistos);
    }

    /**
     * Borra las filas de `$query` cuyo `$columna` ya no viene en `$vistos` --
     * Wialon dejó de devolverlas (unidades/carretas/geocercas son catálogo
     * puro, sin nada más que las referencie, así que acá sí se borran de
     * verdad, a diferencia de los conductores). Con `$vistos` vacío no borra
     * nada: una respuesta vacía de Wialon es más señal de un fallo
     * transitorio que de "ya no queda ninguna", y vaciar todo el catálogo por
     * eso sería mucho peor que dejarlo desactualizado un ciclo.
     *
     * @param  Builder<*>  $query
     * @param  list<int>  $vistos
     */
    private function eliminarNoVistos(Builder $query, string $columna, array $vistos): int
    {
        if ($vistos === []) {
            return 0;
        }

        return $query->whereNotIn($columna, $vistos)->delete();
    }
}
