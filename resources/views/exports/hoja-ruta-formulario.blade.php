<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px 18px 30px 18px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 7px; color: #1f2937; }

        table.encabezado { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.encabezado td { vertical-align: middle; }
        table.encabezado .logo img { height: 40px; }
        table.encabezado .titulo { text-align: right; }
        table.encabezado .titulo .h1 { font-size: 16px; font-weight: bold; color: #b51927; }
        table.encabezado .titulo .h2 { font-size: 12px; font-weight: bold; color: #1f2937; margin-top: 2px; }

        /* Campos del conductor/unidad: SIN bordes, solo la etiqueta resaltada. */
        table.campos { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.campos td { padding: 3px 5px; font-size: 7.5px; }
        table.campos td.etiqueta { font-weight: bold; background: #f3f4f6; text-align: right; white-space: nowrap; width: 15%; }
        table.campos td.valor { width: 35%; }

        table.itinerario, table.documentos { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.itinerario th, table.documentos th {
            background: #b51927; color: #fff; font-weight: bold; padding: 3px 2px;
            font-size: 6px; text-transform: uppercase; border: 0.5px solid #b51927;
        }
        table.itinerario td, table.documentos td {
            padding: 3px 2px; border: 0.5px solid #e5e7eb; text-align: center;
            font-size: 6.5px; word-wrap: break-word;
        }

        .doc-titulo { font-size: 11px; font-weight: bold; color: #b51927; margin: 6px 0 3px; }

        a.archivo { color: #2563eb; text-decoration: underline; }
        .vacio { color: #9ca3af; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="logo" style="width: 40%;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="Segurtrack">
                @endif
            </td>
            <td class="titulo" style="width: 60%;">
                <div class="h1">HOJA DE RUTA</div>
                <div class="h2">N° {{ $idHoja }}</div>
            </td>
        </tr>
    </table>

    <table class="campos">
        <tr>
            <td class="etiqueta">CONDUCTOR:</td>
            <td class="valor">{{ $primera['conductor'] ?? '—' }}</td>
            <td class="etiqueta">COPILOTO:</td>
            <td class="valor">{{ $primera['copiloto'] ?? '—' }}</td>
            <td class="etiqueta">ESTADO:</td>
            <td class="valor">{{ $resumen['estado_label'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">PRECINTOS:</td>
            <td class="valor">{{ $primera['precintos'] ?? '—' }}</td>
            <td class="etiqueta">FECHA INICIO - FECHA FINAL:</td>
            <td class="valor" colspan="3">
                @php($fechaInicio = $resumen['fh_inicio'] ?? $primera['fh_inicio'] ?? null)
                @php($fechaFinal = $resumen['fh_final'] ?? null)
                {{ $fechaInicio ? ($fechaInicio.($fechaFinal ? " - {$fechaFinal}" : '')) : '—' }}
            </td>
        </tr>
        <tr>
            <td class="etiqueta">PLACA:</td>
            <td class="valor">{{ $primera['placa'] ?? '—' }}</td>
            <td class="etiqueta">CARRETA:</td>
            <td class="valor" colspan="3">{{ $primera['carreta'] ?? '—' }}</td>
        </tr>
    </table>

    <table class="itinerario">
        <thead>
            <tr>
                @foreach ($columnasItinerario as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($itinerario as $fila)
                <tr>
                    @foreach ($fila as $celda)
                        <td>{{ $celda !== '' ? $celda : '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="doc-titulo">DOCUMENTOS ADJUNTOS</div>
    <table class="documentos">
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
            @forelse ($documentos as $doc)
                <tr>
                    <td>{{ $doc['tipo'] ?? '—' }}</td>
                    <td>{{ $doc['documento'] ?? '—' }}</td>
                    <td>{{ $doc['producto'] ?? '—' }}</td>
                    <td>{{ $doc['cantidad'] ?? '—' }}</td>
                    <td>{{ $doc['envase'] ?? '—' }}</td>
                    <td>{{ $doc['peso_neto'] ?? '—' }}</td>
                    <td>{{ $doc['peso_bruto'] ?? '—' }}</td>
                    <td>{{ $doc['observacion'] ?? '—' }}</td>
                    <td>
                        @if ($doc['imagen'] ?? null)
                            <a class="archivo" href="{{ $doc['imagen'] }}">Ver Archivo</a>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="vacio">Sin documentos adjuntos.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
