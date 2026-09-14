<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 90px 18px 40px 18px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 7px; color: #1f2937; }

        header { position: fixed; top: -70px; left: 0; right: 0; height: 60px; }
        header img { height: 38px; }
        header .titulo { font-size: 13px; font-weight: bold; color: #b51927; }
        header .meta { font-size: 7px; color: #6b7280; }
        header table { width: 100%; }
        header td { vertical-align: middle; }

        footer { position: fixed; bottom: -28px; left: 0; right: 0; height: 20px;
                 font-size: 6.5px; color: #9ca3af; text-align: right; }

        .filtros { font-size: 7px; color: #6b7280; margin-bottom: 6px; }
        .filtros span { display: inline-block; background: #f3f4f6; border-radius: 3px;
                        padding: 1px 5px; margin-right: 4px; }

        table.datos { width: 100%; border-collapse: collapse; }
        table.datos th { background: #b51927; color: #fff; font-weight: bold;
                         padding: 3px 2px; font-size: 6px; text-transform: uppercase;
                         border: 0.5px solid #b51927; }
        table.datos td { padding: 3px 2px; border: 0.5px solid #e5e7eb; text-align: center;
                         word-wrap: break-word; }
        table.datos tr.par td { background: #fafafa; }
        .hoja-id { font-weight: bold; color: #b51927; }

        .docs { margin: 0; }
        .docs-wrap { padding: 3px 4px 5px 18px; background: #fff; }
        .docs-titulo { font-size: 6px; font-weight: bold; color: #6b7280;
                       text-transform: uppercase; margin-bottom: 2px; }
        table.docs-tbl { width: 100%; border-collapse: collapse; }
        table.docs-tbl th { background: #f3f4f6; color: #374151; font-weight: bold;
                            padding: 2px 3px; font-size: 5.5px; text-align: left;
                            border: 0.5px solid #e5e7eb; }
        table.docs-tbl td { padding: 2px 3px; font-size: 6px; text-align: left;
                            border: 0.5px solid #e5e7eb; }

        .vacio { text-align: center; padding: 20px; color: #9ca3af; font-size: 9px; }
    </style>
</head>
<body>
    <header>
        <table>
            <tr>
                <td style="width: 45%;">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="Segurtrack">
                    @endif
                </td>
                <td style="width: 55%; text-align: right;">
                    <div class="titulo">Hojas de Ruta &mdash; Detallado</div>
                    <div class="meta">Generado: {{ $generado }} &nbsp;·&nbsp; {{ count($filas) }} registro(s)</div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        Segurtrack · HRControl
    </footer>

    @if ($filtros)
        <div class="filtros">
            <strong>Filtros:</strong>
            @foreach ($filtros as $clave => $valor)
                <span>{{ $clave }}: {{ $valor }}</span>
            @endforeach
        </div>
    @endif

    @if (count($filas) === 0)
        <p class="vacio">Sin hojas de ruta para los filtros aplicados.</p>
    @else
        <table class="datos">
            <thead>
                <tr>
                    @foreach ($columnas as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $i => $fila)
                    <tr class="{{ $i % 2 ? 'par' : '' }}">
                        @foreach ($fila['detalle'] as $c => $celda)
                            <td class="{{ $c === 0 ? 'hoja-id' : '' }}">{{ $celda !== '' ? $celda : '—' }}</td>
                        @endforeach
                    </tr>
                    @if (count($fila['documentos']))
                        <tr class="docs">
                            <td colspan="{{ count($columnas) }}" style="padding: 0; border: 0.5px solid #e5e7eb;">
                                <div class="docs-wrap">
                                    <div class="docs-titulo">Documentos adjuntos ({{ count($fila['documentos']) }})</div>
                                    <table class="docs-tbl">
                                        <thead>
                                            <tr>
                                                <th>Tipo</th>
                                                <th>Documento</th>
                                                <th>Producto</th>
                                                <th>Cantidad</th>
                                                <th>Envase</th>
                                                <th>Peso Neto</th>
                                                <th>Peso Bruto</th>
                                                <th>Observación</th>
                                                <th>Archivo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($fila['documentos'] as $doc)
                                                <tr>
                                                    <td>{{ $doc['tipo'] ?? '—' }}</td>
                                                    <td>{{ $doc['documento'] ?? '—' }}</td>
                                                    <td>{{ $doc['producto'] ?? '—' }}</td>
                                                    <td>{{ $doc['cantidad'] ?? '—' }}</td>
                                                    <td>{{ $doc['envase'] ?? '—' }}</td>
                                                    <td>{{ $doc['peso_neto'] ?? '—' }}</td>
                                                    <td>{{ $doc['peso_bruto'] ?? '—' }}</td>
                                                    <td>{{ $doc['observacion'] ?? '—' }}</td>
                                                    <td>{{ $doc['imagen'] ? 'Sí' : '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script('
                $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                $pdf->text(760, 550, $PAGE_NUM . " / " . $PAGE_COUNT, $font, 6.5, array(0.6, 0.6, 0.6));
            ');
        }
    </script>
</body>
</html>
