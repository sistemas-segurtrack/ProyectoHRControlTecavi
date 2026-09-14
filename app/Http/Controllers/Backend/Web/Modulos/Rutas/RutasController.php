<?php

namespace App\Http\Controllers\Backend\Web\Modulos\Rutas;

use App\Http\Controllers\Controller;
use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\Ruta;
use App\Services\HojasRuta\ConsultaHojasRuta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class RutasController extends Controller
{
    /**
     * Opciones válidas para "filas por página".
     *
     * @var list<int>
     */
    private const POR_PAGINA = [10, 25, 50, 100];

    /**
     * Roles con acceso al listado real. "usuario" es la cuenta compartida
     * `tecavi@segurtrack.com` (ver `RutasAccesoController`) — solo lectura,
     * sin Contactos ni exportar (esos siguen siendo `role:admin`).
     *
     * @var list<string>
     */
    private const ROLES_CON_ACCESO = ['admin', 'usuario'];

    public function __construct(private readonly ConsultaHojasRuta $hojas) {}

    /**
     * Listado de hojas de ruta (datos Tecavi) con filtros, métricas y
     * paginación — una fila por HOJA completa (`ConsultaHojasRuta::baseQueryPorRuta()`),
     * no por tramo: "Km/Fecha Hora Inicio" son la primera parada registrada,
     * "Km/Fecha Hora Final" la última hasta ahora, y "Estado" es
     * `ruta.estado` tal cual (no el de ningún tramo).
     *
     * Sin sesión (o con una que no tenga el rol adecuado) esta misma ruta
     * hace de "puerta": en vez de que el middleware `auth` redirija a
     * /login, se sirve la página con `necesitaAcceso = true` y sin datos —
     * el frontend cubre el listado con el formulario de acceso (solo
     * contraseña, ver `RutasAccesoController`).
     */
    public function index(Request $request): Response
    {
        if (! $request->user()?->hasAnyRole(self::ROLES_CON_ACCESO)) {
            return $this->paginaSinAcceso($request);
        }

        $filtros = $this->hojas->filtros($request);

        $porPagina = (int) $request->query('porPagina', 25);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 25;
        }

        $paginador = $this->hojas->baseQueryPorRuta($filtros)
            ->orderByDesc('fh_final')
            ->orderByDesc('ruta.idruta')
            ->paginate($porPagina)
            ->withQueryString();

        $documentos = $this->hojas->documentosPorRuta(collect($paginador->items()));
        $tramos = $this->hojas->tramosPorRuta(collect($paginador->items()));

        $rutas = collect($paginador->items())
            ->map(fn (Ruta $hoja): array => $this->hojas->transformarRuta($hoja, $documentos, $tramos))
            ->values();

        return Inertia::render('Frontend/Modulos/Rutas/Index', [
            'rutas' => $rutas,
            'paginaMeta' => [
                'actual' => $paginador->currentPage(),
                'total' => $paginador->lastPage(),
                'porPagina' => $paginador->perPage(),
                'totalRegistros' => $paginador->total(),
            ],
            'filtros' => $filtros,
            'opciones' => [
                'porPagina' => self::POR_PAGINA,
                'placas' => Ruta::query()->whereNotNull('placa')->where('placa', '!=', '')
                    ->distinct()->orderBy('placa')->pluck('placa'),
                'conductores' => Ruta::query()->whereNotNull('piloto')->where('piloto', '!=', '')
                    ->distinct()->orderBy('piloto')->pluck('piloto'),
                'geocercas' => DetalleRuta::query()->whereNotNull('geocerca')->where('geocerca', '!=', '')
                    ->distinct()->orderBy('geocerca')->pluck('geocerca'),
                'estados' => collect(ConsultaHojasRuta::ESTADOS_RUTA)->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
            ],
            'metricas' => $this->metricas($filtros),
            'necesitaAcceso' => false,
        ]);
    }

    /**
     * Misma página, sin datos: el frontend la cubre con el formulario de
     * acceso (`necesitaAcceso = true`) en vez de que el visitante vea el
     * listado real o un redirect a /login.
     */
    private function paginaSinAcceso(Request $request): Response
    {
        return Inertia::render('Frontend/Modulos/Rutas/Index', [
            'rutas' => [],
            'paginaMeta' => ['actual' => 1, 'total' => 1, 'porPagina' => 25, 'totalRegistros' => 0],
            'filtros' => $this->hojas->filtros($request),
            'opciones' => [
                'porPagina' => self::POR_PAGINA,
                'placas' => [],
                'conductores' => [],
                'geocercas' => [],
                'estados' => collect(ConsultaHojasRuta::ESTADOS_RUTA)->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
            ],
            'metricas' => [
                'total' => 0,
                'enRuta' => 0,
                'finalizadas' => 0,
                'hoy' => 0,
                'totalUnidades' => [],
                'enRutaUnidades' => [],
                'finalizadasUnidades' => [],
                'hoyUnidades' => [],
            ],
            'necesitaAcceso' => true,
        ]);
    }

    /**
     * Métricas del conjunto filtrado (con listas de unidades para el popover).
     * Cuenta por `ruta.estado` (una hoja = una unidad de conteo), igual que
     * ahora el listado — "Registradas Hoy" es la fecha de la primera parada.
     *
     * @param  array<string, string>  $filtros
     * @return array<string, mixed>
     */
    private function metricas(array $filtros): array
    {
        $hoy = now()->toDateString();

        $filas = $this->hojas->baseQueryPorRuta($filtros)
            ->reorder()
            ->limit(3000)
            ->get()
            ->map(function (Ruta $hoja): array {
                /** @var array<string, mixed> $row */
                $row = $hoja->getAttributes();

                return [
                    'estado' => (string) ($row['estado'] ?? ''),
                    'dia' => empty($row['fh_inicio'])
                        ? null
                        : Carbon::parse((string) $row['fh_inicio'])->toDateString(),
                    'unidad' => [
                        'vehiculo' => $row['placa'] ?? null,
                        'ruta' => $row['idruta'] ?? null,
                    ],
                ];
            });

        $unidades = fn ($coleccion) => $coleccion->pluck('unidad')->values();

        $enRuta = $filas->where('estado', Ruta::ACTIVA);
        $finalizadas = $filas->where('estado', Ruta::FINALIZADA);
        $delDia = $filas->where('dia', $hoy);

        return [
            'total' => $filas->count(),
            'enRuta' => $enRuta->count(),
            'finalizadas' => $finalizadas->count(),
            'hoy' => $delDia->count(),
            'totalUnidades' => $unidades($filas),
            'enRutaUnidades' => $unidades($enRuta),
            'finalizadasUnidades' => $unidades($finalizadas),
            'hoyUnidades' => $unidades($delDia),
        ];
    }
}
