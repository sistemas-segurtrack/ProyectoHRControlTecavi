<?php

namespace App\Console\Commands\Wialon;

use App\Models\WialonSTK\WialonUnidad;
use App\Services\Wialon\WialonService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Empuja el contador de kilometraje (odómetro) de una unidad de vuelta a Wialon
 * (`unit/update_mileage_counter`). Caso delicado: solo se permite sobre las
 * unidades listadas en `services.wialon.contador_km_unidades` (salvo `--force`).
 */
class ActualizarContadorKilometraje extends Command
{
    protected $signature = 'wialon:contador
        {unidad : Nombre o placa de la unidad en Wialon}
        {km : Nuevo valor del contador (entero, 0..4294967)}
        {--force : Actualizar aunque la unidad no esté en la lista blanca}';

    protected $description = 'Actualiza el contador de kilometraje de una unidad en Wialon';

    public function handle(WialonService $wialon): int
    {
        $nombre = trim((string) $this->argument('unidad'));
        $kmCrudo = (string) $this->argument('km');

        if (! ctype_digit($kmCrudo)) {
            $this->components->error('El kilometraje debe ser un entero sin decimales.');

            return self::FAILURE;
        }

        $km = (int) $kmCrudo;
        if ($km > WialonService::CONTADOR_KM_MAX) {
            $this->components->error('El máximo permitido es '.WialonService::CONTADOR_KM_MAX.'.');

            return self::FAILURE;
        }

        try {
            $unidad = $wialon->buscarUnidad($nombre);
        } catch (Throwable $e) {
            $this->components->error("Wialon: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($unidad === null) {
            $this->components->error("No se encontró la unidad «{$nombre}» en Wialon.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->permitida($unidad)) {
            $this->components->error(
                "La unidad «{$unidad['nombre']}» no está en la lista blanca ".
                '(services.wialon.contador_km_unidades). Usa --force para forzar.',
            );

            return self::FAILURE;
        }

        $this->components->info(
            "Unidad {$unidad['nombre']} (id {$unidad['wialon_unidad_id']}) — ".
            'contador actual: '.($unidad['contador_kilometraje_km'] ?? 'n/d')." → nuevo: {$km}",
        );

        try {
            $wialon->actualizarContadorKilometraje((int) $unidad['wialon_unidad_id'], $km);
        } catch (Throwable $e) {
            $this->components->error("No se pudo actualizar: {$e->getMessage()}");

            return self::FAILURE;
        }

        WialonUnidad::query()->updateOrCreate(
            ['wialon_unidad_id' => (int) $unidad['wialon_unidad_id']],
            [
                'placa' => $unidad['placa'] ?? null,
                'nombre' => $unidad['nombre'] ?? null,
                'contador_kilometraje_km' => $km,
                'synced_at' => now(),
            ],
        );

        $this->components->info('Contador de kilometraje actualizado en Wialon.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $unidad
     */
    private function permitida(array $unidad): bool
    {
        /** @var list<string> $lista */
        $lista = (array) config('services.wialon.contador_km_unidades', []);

        $permitidas = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $lista);
        $nombre = mb_strtolower(trim((string) ($unidad['nombre'] ?? '')));
        $placa = mb_strtolower(trim((string) ($unidad['placa'] ?? '')));

        return in_array($nombre, $permitidas, true)
            || ($placa !== '' && in_array($placa, $permitidas, true));
    }
}
