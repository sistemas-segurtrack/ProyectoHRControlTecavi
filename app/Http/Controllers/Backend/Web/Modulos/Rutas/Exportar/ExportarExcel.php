<?php

namespace App\Http\Controllers\Backend\Web\Modulos\Rutas\Exportar;

use App\Http\Controllers\Controller;
use App\Services\HojasRuta\ConsultaHojasRuta;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export "Excel (XLSX) (detallado)" del listado de hojas de ruta.
 *
 * Con una hoja de ruta puntual (`?hoja=T000001`, el botón "Exportar esta hoja
 * de ruta" del modal de detalle): documento único con forma de hoja de ruta
 * física — cabecera con logo/datos del conductor, itinerario y documentos
 * adjuntos. Sin ese filtro (el botón "Exportar" del listado): el formato
 * plano de siempre — hoja 1 con una fila por hoja de ruta, hoja 2 con los
 * documentos adjuntos.
 */
class ExportarExcel extends Controller
{
    private const ROJO = 'B51927';

    private const GRIS_CLARO = 'F3F4F6';

    public function __construct(private readonly ConsultaHojasRuta $hojas) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $filtros = $this->hojas->filtros($request);
        $filas = $this->hojas->filas($filtros);

        $libro = new Spreadsheet;

        if ($filtros['hoja'] !== '') {
            $this->hojaFormulario($libro, $filtros['hoja'], $filas);
        } else {
            $this->hojaDetalle($libro, $filas);
            $this->hojaDocumentos($libro, $filas);
        }

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
        $this->estiloCabecera($hoja, 'A1:'.$ultimaCol.'1');
        $hoja->getRowDimension(1)->setRowHeight(22);

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
            'Envase', 'Peso Neto', 'Peso Bruto', 'Observación', 'Archivo',
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
                    (string) ($doc['observacion'] ?? ''),
                    '',
                ], null, 'A'.$fila);
                $this->celdaArchivo($hoja, 'J'.$fila, is_string($doc['imagen'] ?? null) ? $doc['imagen'] : null);
                $fila++;
            }
        }

        $this->estiloCabecera($hoja, 'A1:J1');
        $hoja->getRowDimension(1)->setRowHeight(22);

        foreach (range('A', 'J') as $col) {
            $hoja->getColumnDimension($col)->setAutoSize(true);
        }

        $hoja->freezePane('A2');
    }

    /**
     * Documento único de una hoja de ruta puntual, con la forma de una hoja de
     * ruta física: cabecera (logo, N° de hoja, conductor/copiloto/precintos/
     * placa/carreta/fecha de inicio), itinerario y documentos adjuntos.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    private function hojaFormulario(Spreadsheet $libro, string $idHoja, Collection $filas): void
    {
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Hoja de ruta');
        $hoja->setShowGridlines(false);

        $anchoColumnas = ['A' => 22, 'B' => 16, 'C' => 16, 'D' => 20, 'E' => 20, 'F' => 15, 'G' => 15, 'H' => 15, 'I' => 15, 'J' => 11, 'K' => 11, 'L' => 9, 'M' => 9, 'N' => 30, 'O' => 12];
        foreach ($anchoColumnas as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }

        // El itinerario va en el orden real de la ruta (parada 1, 2, 3…), no
        // el orden "más reciente primero" del listado.
        $itinerario = $filas->sortBy('orden')->values();
        /** @var array<string, mixed> $primera */
        $primera = $itinerario->first() ?? [];
        $resumen = $this->hojas->resumenDeRuta($idHoja);

        $siguiente = $this->encabezadoFormulario($hoja, $idHoja, $primera, $resumen);
        $siguiente = $this->tablaItinerario($hoja, $siguiente + 2, $itinerario);
        $this->tablaDocumentos($hoja, $siguiente + 2, $itinerario);
    }

    /**
     * Logo + "N° de hoja de ruta" y los datos del conductor/unidad. Devuelve
     * la última fila usada.
     *
     * @param  array<string, mixed>  $primera
     * @param  array<string, mixed>|null  $resumen  ver `ConsultaHojasRuta::resumenDeRuta()`
     */
    private function encabezadoFormulario(Worksheet $hoja, string $idHoja, array $primera, ?array $resumen): int
    {
        $ruta = public_path('recursos/logo-segurtrack.png');
        if (is_file($ruta)) {
            $logo = new Drawing;
            $logo->setPath($ruta);
            $logo->setHeight(46);
            $logo->setCoordinates('A1');
            $logo->setOffsetX(4);
            $logo->setOffsetY(6);
            $logo->setWorksheet($hoja);
        }

        $hoja->getRowDimension(1)->setRowHeight(24);
        $hoja->getRowDimension(2)->setRowHeight(20);
        // El título va alineado a la derecha (más profesional que centrado,
        // con el logo a la izquierda).
        $hoja->mergeCells('C1:O1')->setCellValue('C1', 'HOJA DE RUTA');
        $hoja->getStyle('C1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB(self::ROJO);
        $hoja->getStyle('C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $hoja->mergeCells('C2:O2')->setCellValue('C2', 'N° '.$idHoja);
        $hoja->getStyle('C2')->getFont()->setBold(true)->setSize(13);
        $hoja->getStyle('C2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Sin bordes en los campos del encabezado (conductor/copiloto/…):
        // solo la etiqueta resaltada con fondo y el valor al lado.
        $campo = function (string $celdaEtiqueta, string $celdaValorDesde, string $celdaValorHasta, string $etiqueta, string $valor) use ($hoja): void {
            $hoja->setCellValue($celdaEtiqueta, $etiqueta);
            $hoja->getStyle($celdaEtiqueta)->getFont()->setBold(true);
            $hoja->getStyle($celdaEtiqueta)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS_CLARO);
            $hoja->getStyle($celdaEtiqueta)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);

            $hoja->mergeCells("{$celdaValorDesde}:{$celdaValorHasta}")->setCellValue($celdaValorDesde, $valor !== '' ? $valor : '—');
            $hoja->getStyle($celdaValorDesde)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        };

        // Fecha inicio/final y estado son de la HOJA completa (ruta.estado y
        // la primera/última parada registrada) — no del primer tramo, que
        // podría no ser el último si la hoja sigue abierta.
        $fechaInicio = (string) ($resumen['fh_inicio'] ?? $primera['fh_inicio'] ?? '');
        $fechaFinal = (string) ($resumen['fh_final'] ?? '');
        $rangoFechas = $fechaFinal !== '' ? "{$fechaInicio} - {$fechaFinal}" : $fechaInicio;
        $estado = (string) ($resumen['estado_label'] ?? '');

        // 3 campos en la primera fila (Conductor/Copiloto/Estado), 2 en las
        // siguientes (Precintos/Fecha, Placa/Carreta) — pedido explícito del
        // usuario ("esta bien que muestre 3 en 3").
        $hoja->getRowDimension(4)->setRowHeight(18);
        $campo('A4', 'B4', 'D4', 'CONDUCTOR:', (string) ($primera['conductor'] ?? ''));
        $campo('E4', 'F4', 'G4', 'COPILOTO:', (string) ($primera['copiloto'] ?? ''));
        $campo('H4', 'I4', 'O4', 'ESTADO:', $estado);

        $hoja->getRowDimension(5)->setRowHeight(18);
        $campo('A5', 'B5', 'D5', 'PRECINTOS:', (string) ($primera['precintos'] ?? ''));
        $campo('E5', 'F5', 'O5', 'FECHA INICIO - FECHA FINAL:', $rangoFechas);

        $hoja->getRowDimension(6)->setRowHeight(18);
        $campo('A6', 'B6', 'D6', 'PLACA:', (string) ($primera['placa'] ?? ''));
        $campo('E6', 'F6', 'G6', 'CARRETA:', (string) ($primera['carreta'] ?? ''));

        return 6;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $itinerario
     */
    private function tablaItinerario(Worksheet $hoja, int $desde, Collection $itinerario): int
    {
        $hoja->fromArray(ConsultaHojasRuta::COLUMNAS_FORMULARIO, null, 'A'.$desde);

        $fila = $desde + 1;
        foreach ($itinerario as $registro) {
            $hoja->fromArray($this->hojas->filaFormulario($registro), null, 'A'.$fila);
            $fila++;
        }

        $ultimaFila = max($fila - 1, $desde);
        $this->estiloCabecera($hoja, "A{$desde}:O{$desde}");
        $this->conBordes($hoja, "A{$desde}:O{$ultimaFila}");
        $hoja->getStyle('A'.($desde + 1).':O'.$ultimaFila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return $ultimaFila;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $itinerario
     */
    private function tablaDocumentos(Worksheet $hoja, int $desde, Collection $itinerario): void
    {
        $hoja->setCellValue('A'.$desde, 'DOCUMENTOS ADJUNTOS');
        $hoja->getStyle('A'.$desde)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::ROJO);

        $cabecera = $desde + 1;
        $cabeceraColumnas = ['Tipo', 'Documento', 'Producto', 'Cantidad', 'Envase', 'Peso Neto', 'Peso Bruto', 'Observación', 'Archivo'];
        $hoja->fromArray($cabeceraColumnas, null, 'A'.$cabecera);

        /** @var list<array<string, mixed>> $documentos */
        $documentos = $itinerario
            ->flatMap(fn (array $registro): array => $registro['documentos'] ?? [])
            ->all();

        $fila = $cabecera + 1;
        foreach ($documentos as $doc) {
            $hoja->fromArray([
                (string) ($doc['tipo'] ?? ''),
                (string) ($doc['documento'] ?? ''),
                (string) ($doc['producto'] ?? ''),
                (string) ($doc['cantidad'] ?? ''),
                (string) ($doc['envase'] ?? ''),
                (string) ($doc['peso_neto'] ?? ''),
                (string) ($doc['peso_bruto'] ?? ''),
                (string) ($doc['observacion'] ?? ''),
                '',
            ], null, 'A'.$fila);
            $this->celdaArchivo($hoja, 'I'.$fila, is_string($doc['imagen'] ?? null) ? $doc['imagen'] : null);
            $fila++;
        }

        $ultimaFila = max($fila - 1, $cabecera);
        $this->estiloCabecera($hoja, "A{$cabecera}:I{$cabecera}");
        $this->conBordes($hoja, "A{$cabecera}:I{$ultimaFila}");

        if ($documentos === []) {
            $hoja->mergeCells("A{$fila}:I{$fila}")->setCellValue('A'.$fila, 'Sin documentos adjuntos.');
            $hoja->getStyle('A'.$fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $hoja->getStyle('A'.$fila)->getFont()->setItalic(true)->getColor()->setRGB('9CA3AF');
            $this->conBordes($hoja, "A{$fila}:I{$fila}");
        }
    }

    /**
     * Celda "Archivo": hipervínculo a la imagen del documento, mostrando "Ver
     * Archivo" en vez de la URL completa; "—" si no tiene foto adjunta.
     */
    private function celdaArchivo(Worksheet $hoja, string $celda, ?string $url): void
    {
        if ($url === null || $url === '') {
            $hoja->setCellValue($celda, '—');
            $hoja->getStyle($celda)->getFont()->getColor()->setRGB('9CA3AF');

            return;
        }

        $hoja->setCellValue($celda, 'Ver Archivo');
        $hoja->getCell($celda)->getHyperlink()->setUrl($url);
        $hoja->getStyle($celda)->getFont()->setUnderline(true)->getColor()->setRGB('2563EB');
    }

    private function conBordes(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('D1D5DB');
    }

    private function estiloCabecera(Worksheet $hoja, string $rango): void
    {
        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ROJO);
        $estilo->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }
}
