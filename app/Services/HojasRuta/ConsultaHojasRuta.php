<?php

namespace App\Services\HojasRuta;

use App\Models\HRControl\DetalleRuta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consulta de hojas de ruta (datos Tecavi): una fila por detalle de ruta con los
 * datos de la ruta, el orden siguiente y sus documentos. Compartida por el
 * listado web (`RutasController`) y los exportadores XLSX / PDF.
 */
class ConsultaHojasRuta
{
    /**
     * Estados posibles de una hoja de ruta con su etiqueta legible.
     *
     * @var array<string, string>
     */
    public const ESTADOS = [
        DetalleRuta::EN_RUTA => 'EN RUTA',
        DetalleRuta::FINALIZADO => 'FINALIZADO',
    ];

    /**
     * Encabezados del export "detallado", en el orden pedido.
     *
     * @var list<string>
     */
    public const COLUMNAS = [
        'ID Hoja de Ruta',
        'Conductor',
        'Copiloto',
        'Placa',
        'Carreta',
        'Precintos',
        'Geocerca Inicial',
        'Geocerca Final',
        'Coordenada Inicial',
        'Coordenada Final',
        'Fecha Inicial Conductor',
        'Fecha Final Conductor',
        'Fecha Inicial Sistema',
        'Fecha Final Sistema',
        'Diferencia Inicial',
        'Diferencia Final',
        'Km Inicial',
        'Km Final',
        'Estado',
    ];

    /**
     * Encabezados de la tabla de itinerario del export "hoja de ruta" (una sola
     * hoja, formato documento): los datos ya puestos en la cabecera del
     * formulario (conductor, copiloto, placa, carreta, precintos) no se
     * repiten aquí, salvo "Conductor" — igual que en el formato físico.
     *
     * @var list<string>
     */
    public const COLUMNAS_FORMULARIO = [
        'Conductor',
        'Geocerca Inicial',
        'Coordenada Inicial',
        'Fecha Inicial Conductor',
        'Km Inicial',
        'Diferencia Inicial',
        'Geocerca Final',
        'Coordenada Final',
        'Fecha Final Conductor',
        'Fecha Inicial Sistema',
        'Fecha Final Sistema',
        'Diferencia Final',
        'Km Final',
        'Estado',
    ];

    /**
     * @return array<string, string>
     */
    public function filtros(Request $request): array
    {
        return [
            'conductor' => trim((string) $request->query('conductor', '')),
            'placa' => trim((string) $request->query('placa', '')),
            'id' => trim((string) $request->query('id', '')),
            'desde' => trim((string) $request->query('desde', '')),
            'hasta' => trim((string) $request->query('hasta', '')),
            'estado' => trim((string) $request->query('estado', '')),
            'geocerca' => trim((string) $request->query('geocerca', '')),
            'hoja' => trim((string) $request->query('hoja', '')),
            // Acota a un único detalle (`iddetalleRuta`) — el export de una hoja
            // puntual lo usa para que el documento salga tal cual la fila que
            // se ve en el modal, no todo el itinerario de la ruta.
            'detalle' => trim((string) $request->query('detalle', '')),
        ];
    }

    /**
     * Query base: una fila por detalle de ruta con datos de la ruta y del orden siguiente.
     *
     * @param  array<string, string>  $filtros
     * @return Builder<DetalleRuta>
     */
    public function baseQuery(array $filtros): Builder
    {
        $ordenSiguiente = fn (string $columna) => DetalleRuta::query()
            ->from('detalleruta as sig')
            ->select("sig.{$columna}")
            ->whereColumn('sig.ruta_idruta', 'detalleruta.ruta_idruta')
            ->whereColumn('sig.orden', '>', 'detalleruta.orden')
            ->orderBy('sig.orden')
            ->limit(1);

        return DetalleRuta::query()
            ->join('ruta', 'ruta.idruta', '=', 'detalleruta.ruta_idruta')
            ->select([
                'detalleruta.iddetalleRuta',
                'detalleruta.ruta_idruta',
                'detalleruta.orden',
                'detalleruta.geocerca',
                'detalleruta.coordenada',
                'detalleruta.observacion',
                'detalleruta.fhIndicado',
                'detalleruta.estado',
                'detalleruta.kilometraje as km_inicial',
                'detalleruta.fhRegistro as fh_inicio',
                'ruta.placa',
                'ruta.piloto',
                'ruta.copiloto',
                'ruta.precintos',
                'ruta.carreta',
            ])
            ->selectSub($ordenSiguiente('iddetalleRuta'), 'sig_id')
            ->selectSub($ordenSiguiente('kilometraje'), 'km_final')
            ->selectSub($ordenSiguiente('fhRegistro'), 'fh_final')
            ->selectSub($ordenSiguiente('fhIndicado'), 'fh_indicado_final')
            ->selectSub($ordenSiguiente('geocerca'), 'geocerca_final')
            ->selectSub($ordenSiguiente('coordenada'), 'coordenada_final')
            ->when($filtros['conductor'] !== '', fn ($q) => $q->where('ruta.piloto', 'like', "%{$filtros['conductor']}%"))
            ->when($filtros['placa'] !== '', fn ($q) => $q->where('ruta.placa', 'like', "%{$filtros['placa']}%"))
            ->when($filtros['id'] !== '', fn ($q) => $q->where('detalleruta.ruta_idruta', 'like', "%{$filtros['id']}%"))
            ->when(($filtros['hoja'] ?? '') !== '', fn ($q) => $q->where('detalleruta.ruta_idruta', $filtros['hoja']))
            ->when(($filtros['detalle'] ?? '') !== '', fn ($q) => $q->where('detalleruta.iddetalleRuta', $filtros['detalle']))
            ->when($filtros['desde'] !== '', fn ($q) => $q->whereDate('detalleruta.fhRegistro', '>=', $filtros['desde']))
            ->when($filtros['hasta'] !== '', fn ($q) => $q->whereDate('detalleruta.fhRegistro', '<=', $filtros['hasta']))
            ->when($filtros['estado'] !== '', fn ($q) => $q->where('detalleruta.estado', $filtros['estado']))
            ->when($filtros['geocerca'] !== '', fn ($q) => $q->where('detalleruta.geocerca', 'like', "%{$filtros['geocerca']}%"));
    }

    /**
     * Filas ya transformadas (una por detalle de ruta), ordenadas y con sus documentos.
     *
     * @param  array<string, string>  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function filas(array $filtros, int $limite = 20000): Collection
    {
        $registros = $this->baseQuery($filtros)
            ->orderByDesc('detalleruta.fhRegistro')
            ->orderByDesc('detalleruta.iddetalleRuta')
            ->limit($limite)
            ->get();

        $documentos = $this->documentosDe($registros);

        return $registros
            ->map(fn (DetalleRuta $hoja): array => $this->transformar($hoja, $documentos))
            ->values();
    }

    /**
     * Documentos adjuntos (docruta) de los detalles dados, indexados por id de detalle.
     *
     * @param  Collection<int, DetalleRuta>  $hojas
     * @return array<int, list<array<string, mixed>>>
     */
    public function documentosDe(Collection $hojas): array
    {
        $ids = $hojas
            ->flatMap(function (DetalleRuta $hoja): array {
                $row = $hoja->getAttributes();

                return [$row['iddetalleRuta'] ?? null, $row['sig_id'] ?? null];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $filas = DB::table('docruta')
            ->leftJoin('tipodocumento', 'tipodocumento.idtipoDocumento', '=', 'docruta.tipoDocumento_idtipoDocumento')
            ->whereIn('docruta.detalleRuta_iddetalleRuta', $ids)
            ->get([
                'docruta.detalleRuta_iddetalleRuta',
                'docruta.documento',
                'docruta.cantidad',
                'docruta.producto',
                'docruta.envase',
                'docruta.pesoNeto',
                'docruta.pesoBruto',
                'docruta.imagen',
                'tipodocumento.nombre as tipo',
            ]);

        $agrupados = [];

        foreach ($filas as $fila) {
            $d = (array) $fila;
            $idDetalle = (int) ($d['detalleRuta_iddetalleRuta'] ?? 0);

            $agrupados[$idDetalle][] = [
                'tipo' => $d['tipo'] ?? null,
                'documento' => $d['documento'] ?? null,
                'producto' => $d['producto'] ?? null,
                'cantidad' => $d['cantidad'] ?? null,
                'envase' => $d['envase'] ?? null,
                'peso_neto' => $d['pesoNeto'] ?? null,
                'peso_bruto' => $d['pesoBruto'] ?? null,
                'imagen' => $d['imagen'] ?? null,
            ];
        }

        return $agrupados;
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $documentos
     * @return array<string, mixed>
     */
    public function transformar(DetalleRuta $hoja, array $documentos = []): array
    {
        /** @var array<string, mixed> $row */
        $row = $hoja->getAttributes();
        $estado = (string) ($row['estado'] ?? '');

        $idActual = (int) ($row['iddetalleRuta'] ?? 0);
        $idSiguiente = (int) ($row['sig_id'] ?? 0);

        return [
            'id' => $row['iddetalleRuta'] ?? null,
            'hoja' => $row['ruta_idruta'] ?? null,
            'orden' => $row['orden'] ?? null,
            'placa' => $row['placa'] ?? null,
            'carreta' => $row['carreta'] ?? null,
            'conductor' => $row['piloto'] ?? null,
            'copiloto' => $row['copiloto'] ?? null,
            'precintos' => $row['precintos'] ?? null,
            'geocerca' => $row['geocerca'] ?? null,
            'coordenada' => $row['coordenada'] ?? null,
            'geocerca_final' => $row['geocerca_final'] ?? null,
            'coordenada_final' => $row['coordenada_final'] ?? null,
            'observacion' => $row['observacion'] ?? null,
            'km_inicial' => $row['km_inicial'] ?? null,
            'km_final' => $row['km_final'] ?? null,
            // Tabla principal.
            'fh_inicio' => $this->fecha($row['fh_inicio'] ?? null),
            'fh_final' => $this->fecha($row['fh_final'] ?? null),
            // Modal / export — punto inicial: lo indicado por el conductor vs. lo registrado por el sistema.
            'cond_inicial' => $this->fecha($row['fhIndicado'] ?? null),
            'sis_inicial' => $this->fecha($row['fh_inicio'] ?? null),
            'dif_inicial' => $this->diferencia($row['fh_inicio'] ?? null, $row['fhIndicado'] ?? null),
            // Modal / export — punto final (orden siguiente).
            'cond_final' => $this->fecha($row['fh_indicado_final'] ?? null),
            'sis_final' => $this->fecha($row['fh_final'] ?? null),
            'dif_final' => $this->diferencia($row['fh_final'] ?? null, $row['fh_indicado_final'] ?? null),
            'documentos' => array_merge(
                $documentos[$idActual] ?? [],
                $documentos[$idSiguiente] ?? [],
            ),
            'estado' => $estado,
            'estado_label' => self::ESTADOS[$estado] ?? $estado,
        ];
    }

    /**
     * La fila del export "detallado", en el mismo orden que `COLUMNAS`.
     *
     * @param  array<string, mixed>  $fila
     * @return list<string>
     */
    public function filaDetallada(array $fila): array
    {
        return [
            (string) ($fila['hoja'] ?? ''),
            (string) ($fila['conductor'] ?? ''),
            (string) ($fila['copiloto'] ?? ''),
            (string) ($fila['placa'] ?? ''),
            (string) ($fila['carreta'] ?? ''),
            (string) ($fila['precintos'] ?? ''),
            (string) ($fila['geocerca'] ?? ''),
            (string) ($fila['geocerca_final'] ?? ''),
            (string) ($fila['coordenada'] ?? ''),
            (string) ($fila['coordenada_final'] ?? ''),
            (string) ($fila['cond_inicial'] ?? ''),
            (string) ($fila['cond_final'] ?? ''),
            (string) ($fila['sis_inicial'] ?? ''),
            (string) ($fila['sis_final'] ?? ''),
            $this->difTexto($fila['dif_inicial'] ?? null),
            $this->difTexto($fila['dif_final'] ?? null),
            (string) ($fila['km_inicial'] ?? ''),
            (string) ($fila['km_final'] ?? ''),
            (string) ($fila['estado_label'] ?? ''),
        ];
    }

    /**
     * La fila de la tabla de itinerario del export "hoja de ruta" (una sola
     * hoja), en el mismo orden que `COLUMNAS_FORMULARIO`.
     *
     * @param  array<string, mixed>  $fila
     * @return list<string>
     */
    public function filaFormulario(array $fila): array
    {
        return [
            (string) ($fila['conductor'] ?? ''),
            (string) ($fila['geocerca'] ?? ''),
            (string) ($fila['coordenada'] ?? ''),
            (string) ($fila['cond_inicial'] ?? ''),
            (string) ($fila['km_inicial'] ?? ''),
            $this->difTexto($fila['dif_inicial'] ?? null),
            (string) ($fila['geocerca_final'] ?? ''),
            (string) ($fila['coordenada_final'] ?? ''),
            (string) ($fila['cond_final'] ?? ''),
            (string) ($fila['sis_inicial'] ?? ''),
            (string) ($fila['sis_final'] ?? ''),
            $this->difTexto($fila['dif_final'] ?? null),
            (string) ($fila['km_final'] ?? ''),
            (string) ($fila['estado_label'] ?? ''),
        ];
    }

    /**
     * @param  array{texto: string, signo: string}|null  $dif
     */
    private function difTexto(?array $dif): string
    {
        return $dif['texto'] ?? '';
    }

    private function fecha(mixed $valor): ?string
    {
        if (empty($valor)) {
            return null;
        }

        return Carbon::parse((string) $valor)->format('d/m/Y H:i');
    }

    /**
     * Diferencia sistema − conductor, formateada con signo. Positivo = el sistema registró
     * después de la hora indicada por el conductor.
     *
     * @return array{texto: string, signo: string}|null
     */
    private function diferencia(mixed $sistema, mixed $conductor): ?array
    {
        if (empty($sistema) || empty($conductor)) {
            return null;
        }

        $segundos = (int) round(
            Carbon::parse((string) $conductor)->diffInSeconds(Carbon::parse((string) $sistema), false)
        );
        $abs = abs($segundos);
        $horas = intdiv($abs, 3600);
        $minutos = intdiv($abs % 3600, 60);

        $texto = $horas > 0 ? "{$horas}h {$minutos}m" : "{$minutos}m";
        $signo = $segundos > 0 ? 'pos' : ($segundos < 0 ? 'neg' : 'cero');
        $prefijo = $segundos > 0 ? '+' : ($segundos < 0 ? '−' : '');

        return ['texto' => $prefijo.$texto, 'signo' => $signo];
    }
}
