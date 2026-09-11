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

    public function __construct(private readonly ConsultaHojasRuta $hojas) {}

    /**
     * Listado de hojas de ruta (datos Tecavi) con filtros, métricas y paginación.
     */
    public function index(Request $request): Response
    {
        $filtros = $this->hojas->filtros($request);

        $porPagina = (int) $request->query('porPagina', 25);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 25;
        }

        $paginador = $this->hojas->baseQuery($filtros)
            ->orderByDesc('detalleruta.fhRegistro')
            ->orderByDesc('detalleruta.iddetalleRuta')
            ->paginate($porPagina)
            ->withQueryString();

        $documentos = $this->hojas->documentosDe(collect($paginador->items()));

        $rutas = collect($paginador->items())
            ->map(fn (DetalleRuta $hoja): array => $this->hojas->transformar($hoja, $documentos))
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
                'estados' => collect(ConsultaHojasRuta::ESTADOS)->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
            ],
            'metricas' => $this->metricas($filtros),
        ]);
    }

    /**
     * Métricas del conjunto filtrado (con listas de unidades para el popover).
     *
     * @param  array<string, string>  $filtros
     * @return array<string, mixed>
     */
    private function metricas(array $filtros): array
    {
        $hoy = now()->toDateString();

        $filas = $this->hojas->baseQuery($filtros)
            ->reorder()
            ->orderByDesc('detalleruta.fhRegistro')
            ->limit(3000)
            ->get()
            ->map(function (DetalleRuta $hoja): array {
                /** @var array<string, mixed> $row */
                $row = $hoja->getAttributes();

                return [
                    'estado' => (string) ($row['estado'] ?? ''),
                    'dia' => empty($row['fh_inicio'])
                        ? null
                        : Carbon::parse((string) $row['fh_inicio'])->toDateString(),
                    'unidad' => [
                        'vehiculo' => $row['placa'] ?? null,
                        'ruta' => $row['ruta_idruta'] ?? null,
                    ],
                ];
            });

        $unidades = fn ($coleccion) => $coleccion->pluck('unidad')->values();

        $enRuta = $filas->where('estado', DetalleRuta::EN_RUTA);
        $finalizadas = $filas->where('estado', DetalleRuta::FINALIZADO);
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
