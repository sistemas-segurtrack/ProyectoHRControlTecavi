<?php

namespace App\Services\Wialon;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Cliente de la Remote API de Wialon (Segurtrack): obtiene un `sid` con el token
 * configurado y expone los catálogos que consume la PWA Conductor.
 *
 * @see https://sdk.wialon.com/wiki/en/sidebar/remoteapi/apiref/apiref
 */
class WialonService
{
    private const CACHE_SID = 'wialon:sid';

    /**
     * Tope de `unit/update_mileage_counter`: entero sin decimales.
     */
    public const CONTADOR_KM_MAX = 4294967;

    /**
     * Flags de `avl_unit`: base (0x1) + campos de perfil (0x800000) + contadores (0x2000).
     */
    private const FLAGS_UNIDAD = 8396801;

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token,
        private readonly string $recurso,
        private readonly int $sidTtlMinutes,
    ) {}

    /**
     * Lista de conductores del recurso configurado.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function conductores(): Collection
    {
        $items = $this->searchItems('avl_resource', 257);

        /** @var Collection<int, array<string, mixed>> $conductores */
        $conductores = $this->recursoConfigurado($items)
            ->flatMap(fn (array $recurso) => array_values((array) ($recurso['drvrs'] ?? [])))
            ->map(fn (array $d): array => [
                'wialon_conductor_id' => (int) ($d['id'] ?? 0),
                'codigo' => $this->limpiar($d['c'] ?? null),
                'dni' => $this->limpiar($d['jp']['DNI'] ?? null),
                'licencia' => $this->limpiar($d['jp']['LICENCIA'] ?? null),
                'nombre' => trim((string) ($d['n'] ?? '')),
                'telefono' => $this->limpiar($d['p'] ?? null),
                'descripcion' => $this->limpiar($d['ds'] ?? null),
                'pwd' => $this->limpiar($d['pwd'] ?? null),
            ])
            ->filter(fn (array $d) => $d['nombre'] !== '')
            ->values();

        return $conductores;
    }

    /**
     * Unidades de Wialon con su placa (campo de perfil `registration_plate`) y su
     * contador de kilometraje (`cnm_km`). La placa puede ser `null`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function unidades(): Collection
    {
        /** @var Collection<int, array<string, mixed>> $unidades */
        $unidades = collect($this->searchItems('avl_unit', self::FLAGS_UNIDAD))
            ->map(fn (array $item): array => [
                'wialon_unidad_id' => (int) ($item['id'] ?? 0),
                'placa' => $this->placaDe($item),
                'nombre' => $this->limpiar($item['nm'] ?? null),
                'contador_kilometraje_km' => isset($item['cnm_km']) ? (int) $item['cnm_km'] : null,
            ])
            ->filter(fn (array $u) => $u['wialon_unidad_id'] > 0)
            ->values();

        return $unidades;
    }

    /**
     * Busca una unidad de Wialon por su nombre (`nm`) o su placa (case-insensitive).
     *
     * @return array<string, mixed>|null
     */
    public function buscarUnidad(string $nombreOPlaca): ?array
    {
        $aguja = mb_strtolower(trim($nombreOPlaca));

        foreach ($this->searchItems('avl_unit', self::FLAGS_UNIDAD) as $item) {
            $nombre = mb_strtolower(trim((string) ($item['nm'] ?? '')));
            $placa = mb_strtolower((string) ($this->placaDe($item) ?? ''));

            if ($aguja !== '' && ($aguja === $nombre || $aguja === $placa)) {
                return [
                    'wialon_unidad_id' => (int) ($item['id'] ?? 0),
                    'nombre' => $this->limpiar($item['nm'] ?? null),
                    'placa' => $this->placaDe($item),
                    'contador_kilometraje_km' => isset($item['cnm_km']) ? (int) $item['cnm_km'] : null,
                ];
            }
        }

        return null;
    }

    /**
     * Actualiza el contador de kilometraje (odómetro) de una unidad en Wialon vía
     * `unit/update_mileage_counter`. El valor es un entero entre 0 y 4 294 967.
     *
     * @param  int  $itemId  id de la unidad en Wialon
     * @param  int  $km  nuevo valor del contador (sin decimales)
     */
    public function actualizarContadorKilometraje(int $itemId, int $km): int
    {
        if ($km < 0 || $km > self::CONTADOR_KM_MAX) {
            throw new InvalidArgumentException(
                'El contador de kilometraje debe ser un entero entre 0 y '.self::CONTADOR_KM_MAX.'.',
            );
        }

        $this->call('unit/update_mileage_counter', [
            'itemId' => $itemId,
            'newValue' => $km,
        ]);

        return $km;
    }

    /**
     * Placa (`registration_plate`) de un item de `avl_unit`, o `null`.
     *
     * @param  array<string, mixed>  $item
     */
    private function placaDe(array $item): ?string
    {
        $campo = collect((array) ($item['pflds'] ?? []))
            ->first(fn ($f) => is_array($f) && ($f['n'] ?? null) === 'registration_plate');

        $placa = is_array($campo) ? ($campo['v'] ?? null) : null;

        return (is_string($placa) && trim($placa) !== '') ? trim($placa) : null;
    }

    /**
     * Carretas (trailers) del recurso configurado.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function carretas(): Collection
    {
        $items = $this->searchItems('avl_resource', 65537);

        /** @var Collection<int, array<string, mixed>> $carretas */
        $carretas = $this->recursoConfigurado($items)
            ->flatMap(fn (array $recurso) => collect(array_values((array) ($recurso['trlrs'] ?? [])))
                ->map(fn (array $t): array => [
                    'wialon_carreta_id' => (int) ($t['id'] ?? 0),
                    'recurso' => (string) ($recurso['nm'] ?? ''),
                    'nombre' => trim((string) ($t['n'] ?? '')),
                ]))
            ->filter(fn (array $t) => $t['nombre'] !== '')
            ->values();

        return $carretas;
    }

    /**
     * Geocercas (zones library) del recurso configurado. Solo el nombre.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function geocercas(): Collection
    {
        $items = $this->searchItems('avl_resource', 4097, 'zones_library');

        /** @var Collection<int, array<string, mixed>> $geocercas */
        $geocercas = $this->recursoConfigurado($items)
            ->flatMap(fn (array $recurso) => collect(array_values((array) ($recurso['zl'] ?? [])))
                ->map(fn (array $z): array => [
                    'wialon_geocerca_id' => (int) ($z['id'] ?? 0),
                    'recurso' => (string) ($recurso['nm'] ?? ''),
                    'nombre' => trim((string) ($z['n'] ?? '')),
                ]))
            ->filter(fn (array $z) => $z['nombre'] !== '')
            ->values();

        return $geocercas;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchItems(string $itemsType, int $flags, string $propName = '*'): array
    {
        $params = [
            'spec' => [
                'itemsType' => $itemsType,
                'propName' => $propName,
                'propValueMask' => '*',
                'sortType' => $propName === '*' ? 'sys_name' : $propName,
                'propType' => 'propitemname',
            ],
            'force' => 1,
            'flags' => $flags,
            'from' => 0,
            'to' => 0,
        ];

        $data = $this->call('core/search_items', $params);

        /** @var array<int, array<string, mixed>> $items */
        $items = $data['items'] ?? [];

        return $items;
    }

    /**
     * Ejecuta un servicio de la API renovando el sid si expiró.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(string $svc, array $params): array
    {
        $ejecutar = fn (string $sid): array => Http::asForm()
            ->acceptJson()
            ->post($this->endpoint(), [
                'svc' => $svc,
                'params' => json_encode($params, JSON_THROW_ON_ERROR),
                'sid' => $sid,
            ])
            ->throw()
            ->json() ?? [];

        $data = $ejecutar($this->sid());

        // error 1 = sesión inválida/expirada → renovar sid y reintentar una vez.
        if (($data['error'] ?? null) === 1) {
            Cache::forget(self::CACHE_SID);
            $data = $ejecutar($this->sid());
        }

        if (isset($data['error'])) {
            throw new RuntimeException("Wialon {$svc} devolvió error {$data['error']}");
        }

        return $data;
    }

    private function sid(): string
    {
        return Cache::remember(self::CACHE_SID, now()->addMinutes($this->sidTtlMinutes), function (): string {
            if (blank($this->token)) {
                throw new RuntimeException('WIALON_STK_TOKEN no está configurado.');
            }

            $data = Http::asForm()
                ->acceptJson()
                ->post($this->endpoint(), [
                    'svc' => 'token/login',
                    'params' => json_encode(['token' => $this->token], JSON_THROW_ON_ERROR),
                ])
                ->throw()
                ->json() ?? [];

            if (empty($data['eid'])) {
                throw new RuntimeException('Wialon token/login no devolvió sesión (eid).');
            }

            return (string) $data['eid'];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function recursoConfigurado(array $items): Collection
    {
        return collect($items)
            ->filter(fn (array $item) => strcasecmp((string) ($item['nm'] ?? ''), $this->recurso) === 0)
            ->values();
    }

    private function endpoint(): string
    {
        return rtrim($this->baseUrl, '/').'/wialon/ajax.html';
    }

    private function limpiar(mixed $valor): ?string
    {
        $valor = is_string($valor) ? trim($valor) : null;

        return ($valor === null || $valor === '') ? null : $valor;
    }
}
