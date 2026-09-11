<?php

namespace App\Http\Controllers\Backend\Web\Modulos\Rutas\Exportar;

use App\Http\Controllers\Controller;
use App\Services\HojasRuta\ConsultaHojasRuta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export "PDF" de hojas de ruta.
 *
 * Con una hoja de ruta puntual (`?hoja=T000001`, el botón "Exportar esta hoja
 * de ruta" del modal de detalle): documento único A4 vertical con la misma
 * forma que el export XLSX equivalente — cabecera con logo/datos del
 * conductor, itinerario y documentos adjuntos. Sin ese filtro (el botón
 * "Exportar" del listado): A4 apaisado, una fila por hoja con las 19
 * columnas y su tabla de documentos adjuntos.
 */
class ExportarPdf extends Controller
{
    public function __construct(private readonly ConsultaHojasRuta $hojas) {}

    public function __invoke(Request $request): Response
    {
        $filtros = $this->hojas->filtros($request);
        $filas = $this->hojas->filas($filtros, 5000);

        if ($filtros['hoja'] !== '') {
            return $this->pdfFormulario($filtros, $filas);
        }

        $pdf = Pdf::loadView('exports.hojas-ruta', [
            'columnas' => ConsultaHojasRuta::COLUMNAS,
            'filas' => $filas->map(fn (array $fila): array => [
                'detalle' => $this->hojas->filaDetallada($fila),
                'documentos' => $fila['documentos'] ?? [],
            ])->all(),
            'filtros' => array_filter($filtros, static fn (string $v): bool => $v !== ''),
            'logo' => $this->logo(),
            'generado' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('hojas-ruta-listado-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * Documento único de una hoja de ruta puntual (A4 vertical), con la misma
     * forma que `ExportarExcel::hojaFormulario()`.
     *
     * @param  array<string, string>  $filtros
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    private function pdfFormulario(array $filtros, Collection $filas): Response
    {
        // El itinerario va en el orden real de la ruta (parada 1, 2, 3…).
        $itinerario = $filas->sortBy('orden')->values();
        /** @var array<string, mixed> $primera */
        $primera = $itinerario->first() ?? [];

        /** @var list<array<string, mixed>> $documentos */
        $documentos = $itinerario->flatMap(fn (array $fila): array => $fila['documentos'] ?? [])->all();

        $pdf = Pdf::loadView('exports.hoja-ruta-formulario', [
            'idHoja' => $filtros['hoja'],
            'logo' => $this->logo(),
            'primera' => $primera,
            'columnasItinerario' => ConsultaHojasRuta::COLUMNAS_FORMULARIO,
            'itinerario' => $itinerario->map(fn (array $fila): array => $this->hojas->filaFormulario($fila))->all(),
            'documentos' => $documentos,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("hoja-ruta-{$filtros['hoja']}-".now()->format('Ymd-His').'.pdf');
    }

    /**
     * Logo de Segurtrack como data URI (dompdf no accede a la ruta pública directamente).
     */
    private function logo(): ?string
    {
        $ruta = public_path('recursos/logo-segurtrack.png');

        if (! is_file($ruta)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($ruta));
    }
}
