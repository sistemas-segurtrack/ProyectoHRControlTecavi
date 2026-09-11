<?php

namespace App\Http\Controllers\Backend\Web\Modulos\Rutas\Exportar;

use App\Http\Controllers\Controller;
use App\Services\HojasRuta\ConsultaHojasRuta;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export "Excel (XLSX) (detallado)" del listado de hojas de ruta.
 *
 * Hoja 1: una fila por hoja de ruta con las 19 columnas del detalle.
 * Hoja 2: los documentos adjuntos, referenciados por ID de hoja de ruta.
 */
class ExportarExcel extends Controller
{
    public function __construct(private readonly ConsultaHojasRuta $hojas) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $filtros = $this->hojas->filtros($request);
        $filas = $this->hojas->filas($filtros);

        $libro = new Spreadsheet;
        $this->hojaDetalle($libro, $filas);
        $this->hojaDocumentos($libro, $filas);
        $libro->setActiveSheetIndex(0);

        $writer = new Xlsx($libro);
        $sufijo = $filtros['hoja'] !== '' ? $filtros['hoja'] : 'listado';
        $nombre = "hojas-ruta-{$sufijo}-".now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $nombre,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache',
            ],
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    private function hojaDetalle(Spreadsheet $libro, Collection $filas): void
    {
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Hojas de ruta');

        $hoja->fromArray(ConsultaHojasRuta::COLUMNAS, null, 'A1');

        $fila = 2;
        foreach ($filas as $registro) {
            $hoja->fromArray($this->hojas->filaDetallada($registro), null, 'A'.$fila);
            $fila++;
        }

        $ultimaCol = $hoja->getHighestColumn();
        $this->estiloCabecera($hoja, $ultimaCol.'1');

        foreach (range('A', $ultimaCol) as $col) {
            $hoja->getColumnDimension($col)->setAutoSize(true);
        }

        $hoja->freezePane('A2');
        if ($fila > 2) {
            $hoja->setAutoFilter("A1:{$ultimaCol}".($fila - 1));
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    private function hojaDocumentos(Spreadsheet $libro, Collection $filas): void
    {
        $hoja = $libro->createSheet();
        $hoja->setTitle('Documentos adjuntos');

        $cabecera = [
            'ID Hoja de Ruta', 'Tipo', 'Documento', 'Producto', 'Cantidad',
            'Envase', 'Peso Neto', 'Peso Bruto', 'Archivo',
        ];
        $hoja->fromArray($cabecera, null, 'A1');

        $fila = 2;
        foreach ($filas as $registro) {
            /** @var list<array<string, mixed>> $documentos */
            $documentos = $registro['documentos'] ?? [];

            foreach ($documentos as $doc) {
                $hoja->fromArray([
                    (string) ($registro['hoja'] ?? ''),
                    (string) ($doc['tipo'] ?? ''),
                    (string) ($doc['documento'] ?? ''),
                    (string) ($doc['producto'] ?? ''),
                    (string) ($doc['cantidad'] ?? ''),
                    (string) ($doc['envase'] ?? ''),
                    (string) ($doc['peso_neto'] ?? ''),
                    (string) ($doc['peso_bruto'] ?? ''),
                    (string) ($doc['imagen'] ?? ''),
                ], null, 'A'.$fila);
                $fila++;
            }
        }

        $this->estiloCabecera($hoja, 'I1');

        foreach (range('A', 'I') as $col) {
            $hoja->getColumnDimension($col)->setAutoSize(true);
        }

        $hoja->freezePane('A2');
    }

    private function estiloCabecera(Worksheet $hoja, string $hasta): void
    {
        $rango = 'A1:'.$hasta;
        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B51927');
        $estilo->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension(1)->setRowHeight(22);
    }
}
