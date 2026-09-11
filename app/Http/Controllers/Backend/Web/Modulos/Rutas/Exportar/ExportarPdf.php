<?php

namespace App\Http\Controllers\Backend\Web\Modulos\Rutas\Exportar;

use App\Http\Controllers\Controller;
use App\Services\HojasRuta\ConsultaHojasRuta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export "PDF (detallado)" del listado de hojas de ruta: A4 apaisado, una fila
 * por hoja con las 19 columnas y, debajo, su tabla de documentos adjuntos.
 */
class ExportarPdf extends Controller
{
    public function __construct(private readonly ConsultaHojasRuta $hojas) {}

    public function __invoke(Request $request): Response
    {
        $filtros = $this->hojas->filtros($request);
        $filas = $this->hojas->filas($filtros, 5000);

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

        $sufijo = $filtros['hoja'] !== '' ? $filtros['hoja'] : 'listado';

        return $pdf->download("hojas-ruta-{$sufijo}-".now()->format('Ymd-His').'.pdf');
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
