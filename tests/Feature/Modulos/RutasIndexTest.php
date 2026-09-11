<?php

use App\Models\HRControl\Contacto;
use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\DocRuta;
use App\Models\HRControl\Ruta;
use App\Models\User;
use App\Services\HojasRuta\ConsultaHojasRuta;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

test('un invitado es redirigido al login', function () {
    $this->get(route('modulos.rutas.index'))->assertRedirect(route('login'));
});

test('un usuario sin rol admin no puede entrar', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('modulos.rutas.index'))
        ->assertForbidden();
});

test('el admin ve el listado de hojas de ruta', function () {
    $ruta = Ruta::factory()->create([
        'idruta' => 'T000042',
        'placa' => 'ABC-123',
        'piloto' => 'Juan Perez',
    ]);
    $contacto = Contacto::factory()->create();

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
        'kilometraje' => '100',
    ]);

    $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Modulos/Rutas/Index')
            ->has('rutas', 1)
            ->where('rutas.0.hoja', 'T000042')
            ->where('rutas.0.placa', 'ABC-123')
            ->where('rutas.0.conductor', 'Juan Perez')
            ->where('metricas.total', 1)
            ->where('metricas.enRuta', 1)
        );
});

test('el filtro ID Hoja de Ruta busca por el código de la ruta', function () {
    $contacto = Contacto::factory()->create();
    $rutaA = Ruta::factory()->create(['idruta' => 'T000100']);
    $rutaB = Ruta::factory()->create(['idruta' => 'T000200']);

    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $rutaA->idruta, 'orden' => 1]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $rutaB->idruta, 'orden' => 1]);

    $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.index', ['id' => 'T000100']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rutas', 1)
            ->where('rutas.0.hoja', 'T000100')
        );
});

test('el km y la fecha final se toman del siguiente orden', function () {
    $ruta = Ruta::factory()->create();
    $contacto = Contacto::factory()->create();

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
        'kilometraje' => '100',
        'fhRegistro' => '2026-01-01 08:00:00',
    ]);

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 2,
        'kilometraje' => '250',
        'fhRegistro' => '2026-01-01 15:30:00',
    ]);

    $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rutas', 2)
            ->where('rutas.1.km_inicial', '100')
            ->where('rutas.1.km_final', '250')
            ->where('rutas.1.fh_final', '01/01/2026 15:30')
            ->where('rutas.0.km_final', null)
        );
});

test('el filtro por placa acota los resultados', function () {
    $contacto = Contacto::factory()->create();

    $rutaA = Ruta::factory()->create(['placa' => 'AAA-111']);
    $rutaB = Ruta::factory()->create(['placa' => 'BBB-222']);

    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $rutaA->idruta, 'orden' => 1]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $rutaB->idruta, 'orden' => 1]);

    $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.index', ['placa' => 'AAA']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rutas', 1)
            ->where('rutas.0.placa', 'AAA-111')
        );
});

test('la fila expone la comparación conductor/sistema y los documentos para el modal', function () {
    $contacto = Contacto::factory()->create();
    $ruta = Ruta::factory()->create(['idruta' => 'T000009']);

    $d1 = DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
        'geocerca' => 'PLANTA A',
        'fhIndicado' => '2026-01-01 08:00:00',
        'fhRegistro' => '2026-01-01 08:20:00',
    ]);

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 2,
        'geocerca' => 'PLANTA B',
        'fhIndicado' => '2026-01-01 16:00:00',
        'fhRegistro' => '2026-01-01 15:30:00',
    ]);

    DocRuta::create([
        'detalleRuta_iddetalleRuta' => $d1->iddetalleRuta,
        'tipoDocumento_idtipoDocumento' => 1,
        'documento' => 'GRE-001',
        'producto' => 'Cemento',
        'cantidad' => '10',
    ]);

    $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rutas.1.geocerca', 'PLANTA A')
            ->where('rutas.1.geocerca_final', 'PLANTA B')
            ->where('rutas.1.cond_inicial', '01/01/2026 08:00')
            ->where('rutas.1.sis_inicial', '01/01/2026 08:20')
            ->where('rutas.1.dif_inicial.texto', '+20m')
            ->where('rutas.1.dif_inicial.signo', 'pos')
            ->where('rutas.1.dif_final.signo', 'neg')
            ->has('rutas.1.documentos', 1)
            ->where('rutas.1.documentos.0.documento', 'GRE-001')
        );
});

test('el admin exporta el listado detallado a XLSX', function () {
    $contacto = Contacto::factory()->create();
    $ruta = Ruta::factory()->create(['idruta' => 'T000777', 'placa' => 'EXP-001']);

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
    ]);

    $response = $this->actingAs(crearAdmin())->get(route('modulos.rutas.exportar.excel'));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('spreadsheetml')
        ->and($response->headers->get('content-disposition'))->toContain('.xlsx');
});

test('el admin exporta el listado detallado a PDF con el logo', function () {
    $contacto = Contacto::factory()->create();
    $ruta = Ruta::factory()->create(['idruta' => 'T000778', 'placa' => 'PDF-001']);

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
    ]);

    $response = $this->actingAs(crearAdmin())->get(route('modulos.rutas.exportar.pdf'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF-');
});

test('el export por hoja acota a una sola hoja de ruta y arma el formulario A4 vertical', function () {
    $contacto = Contacto::factory()->create();
    $a = Ruta::factory()->create(['idruta' => 'T000801', 'placa' => 'AAA-111']);
    $b = Ruta::factory()->create(['idruta' => 'T000802', 'placa' => 'BBB-222']);
    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $a->idruta, 'orden' => 1]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $b->idruta, 'orden' => 1]);

    $response = $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.exportar.pdf', ['hoja' => 'T000801']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF-')
        ->and($response->headers->get('content-disposition'))->toContain('T000801');
});

test('el formulario A4 de una hoja puntual (misma vista del PDF) excluye datos de otras hojas', function () {
    $contacto = Contacto::factory()->create();
    $a = Ruta::factory()->create(['idruta' => 'T000803', 'piloto' => 'PILOTO UNO']);
    $b = Ruta::factory()->create(['idruta' => 'T000804', 'piloto' => 'PILOTO DOS']);
    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $a->idruta, 'orden' => 1, 'geocerca' => 'GEOCERCA UNO']);
    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $b->idruta, 'orden' => 1, 'geocerca' => 'GEOCERCA DOS']);

    $hojas = app(ConsultaHojasRuta::class);
    $filtros = $hojas->filtros(Request::create('/', 'GET', ['hoja' => 'T000803']));
    $filas = $hojas->filas($filtros);

    $html = view('exports.hoja-ruta-formulario', [
        'idHoja' => $filtros['hoja'],
        'logo' => null,
        'primera' => $filas->first() ?? [],
        'columnasItinerario' => ConsultaHojasRuta::COLUMNAS_FORMULARIO,
        'itinerario' => $filas->map(fn (array $f) => $hojas->filaFormulario($f))->all(),
        'documentos' => [],
    ])->render();

    expect($html)->toContain('HOJA DE RUTA', 'PILOTO UNO', 'GEOCERCA UNO')
        ->not->toContain('PILOTO DOS', 'GEOCERCA DOS');
});

test('el export XLSX de una sola hoja arma el formulario con cabecera, itinerario y documentos', function () {
    $contacto = Contacto::factory()->create();
    $ruta = Ruta::factory()->create([
        'idruta' => 'T000900',
        'placa' => 'FRM-001',
        'carreta' => 'CARR-01',
        'piloto' => 'CARLOS FORMULARIO',
        'copiloto' => 'ANA COPILOTO',
        'precintos' => 'PRE-9',
    ]);

    $d1 = DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
        'geocerca' => 'PLANTA A',
        'kilometraje' => '100',
        'fhRegistro' => '2026-01-01 08:00:00',
        'estado' => DetalleRuta::EN_RUTA,
    ]);

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 2,
        'geocerca' => 'PLANTA B',
        'kilometraje' => '250',
        'fhRegistro' => '2026-01-01 15:30:00',
        'estado' => DetalleRuta::FINALIZADO,
    ]);

    DocRuta::create([
        'detalleRuta_iddetalleRuta' => $d1->iddetalleRuta,
        'tipoDocumento_idtipoDocumento' => 1,
        'documento' => 'GRE-900',
        'producto' => 'Cemento',
        'cantidad' => '10',
        'imagen' => 'https://tools.segurtrack.com/hrcontrol/storage/docruta/foto-900.jpg',
    ]);

    $response = $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.exportar.excel', ['hoja' => 'T000900']));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('T000900');

    $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($archivo, $response->streamedContent());
    $libro = (new XlsxReader)->load($archivo);
    unlink($archivo);

    expect($libro->getSheetCount())->toBe(1)
        ->and($libro->getActiveSheet()->getTitle())->toBe('Hoja de ruta');

    $hoja = $libro->getActiveSheet();
    expect($hoja->getCell('C1')->getValue())->toBe('HOJA DE RUTA')
        ->and($hoja->getStyle('C1')->getAlignment()->getHorizontal())->toBe(Alignment::HORIZONTAL_RIGHT)
        ->and($hoja->getCell('C2')->getValue())->toBe('N° T000900')
        // Sin bordes en los campos del encabezado.
        ->and($hoja->getStyle('A4:D4')->getBorders()->getBottom()->getBorderStyle())->toBe(Border::BORDER_NONE)
        ->and($hoja->getCell('B4')->getValue())->toBe('CARLOS FORMULARIO')
        ->and($hoja->getCell('F4')->getValue())->toBe('ANA COPILOTO')
        ->and($hoja->getCell('I4')->getValue())->toBe('PRE-9')
        ->and($hoja->getCell('B5')->getValue())->toBe('FRM-001')
        ->and($hoja->getCell('F5')->getValue())->toBe('CARR-01')
        // Cabecera del itinerario en la fila 7 (encabezado: filas 1-5, fila 6 en blanco).
        // Orden: todo lo "inicial" primero, luego todo lo "final"; dentro de
        // cada bloque, Sistema antes que Conductor, y Km antes que Diferencia.
        ->and($hoja->getCell('A7')->getValue())->toBe('Conductor')
        ->and($hoja->getCell('B7')->getValue())->toBe('Geocerca Inicial')
        ->and($hoja->getCell('D7')->getValue())->toBe('Fecha Inicial Sistema')
        ->and($hoja->getCell('E7')->getValue())->toBe('Fecha Inicial Conductor')
        ->and($hoja->getCell('F7')->getValue())->toBe('Km Inicial')
        ->and($hoja->getCell('G7')->getValue())->toBe('Diferencia Inicial')
        ->and($hoja->getCell('H7')->getValue())->toBe('Geocerca Final')
        ->and($hoja->getCell('L7')->getValue())->toBe('Km Final')
        ->and($hoja->getCell('M7')->getValue())->toBe('Diferencia Final')
        // Itinerario en orden (parada 1 primero).
        ->and($hoja->getCell('B8')->getValue())->toBe('PLANTA A')
        ->and($hoja->getCell('H8')->getValue())->toBe('PLANTA B')
        ->and((string) $hoja->getCell('F8')->getValue())->toBe('100')
        ->and((string) $hoja->getCell('L8')->getValue())->toBe('250');

    // "DOCUMENTOS ADJUNTOS" y su tabla, en algún lado más abajo.
    $texto = [];
    foreach ($hoja->getRowIterator() as $fila) {
        foreach ($fila->getCellIterator() as $celda) {
            $valor = $celda->getValue();
            if ($valor !== null && $valor !== '') {
                $texto[] = (string) $valor;
            }
        }
    }
    expect($texto)->toContain('DOCUMENTOS ADJUNTOS', 'Documento', 'GRE-900', 'Cemento', 'Ver Archivo');

    // La celda "Archivo" es un hipervínculo, no la URL como texto plano
    // (encabezado: filas 1-5; itinerario: cabecera fila 7 + 2 paradas → filas
    // 8-9; documentos: título fila 11, cabecera fila 12, primer documento 13).
    expect($hoja->getCell('H13')->getValue())->toBe('Ver Archivo')
        ->and($hoja->getCell('H13')->getHyperlink()->getUrl())->toBe('https://tools.segurtrack.com/hrcontrol/storage/docruta/foto-900.jpg');
});

test('el export XLSX de una hoja acota a un solo detalle cuando se pide (tal cual el modal)', function () {
    $contacto = Contacto::factory()->create();
    $ruta = Ruta::factory()->create(['idruta' => 'T000910', 'placa' => 'DET-001']);

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 1, 'geocerca' => 'PARADA 1', 'kilometraje' => '10',
    ]);
    $d2 = DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 2, 'geocerca' => 'PARADA 2', 'kilometraje' => '20',
    ]);

    $response = $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.exportar.excel', ['hoja' => 'T000910', 'detalle' => $d2->iddetalleRuta]));

    $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($archivo, $response->streamedContent());
    $libro = (new XlsxReader)->load($archivo);
    unlink($archivo);

    $hoja = $libro->getActiveSheet();
    $texto = [];
    foreach ($hoja->getRowIterator() as $fila) {
        foreach ($fila->getCellIterator() as $celda) {
            $valor = $celda->getValue();
            if ($valor !== null && $valor !== '') {
                $texto[] = (string) $valor;
            }
        }
    }

    expect($texto)->toContain('PARADA 2')->and($texto)->not->toContain('PARADA 1');
});

test('el export XLSX sin hoja puntual sigue usando el listado plano de siempre', function () {
    $contacto = Contacto::factory()->create();
    $ruta = Ruta::factory()->create(['idruta' => 'T000901', 'placa' => 'LST-001']);
    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $ruta->idruta, 'orden' => 1]);

    $response = $this->actingAs(crearAdmin())->get(route('modulos.rutas.exportar.excel'));

    $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($archivo, $response->streamedContent());
    $libro = (new XlsxReader)->load($archivo);
    unlink($archivo);

    expect($libro->getSheetCount())->toBe(2)
        ->and($libro->getSheet(0)->getTitle())->toBe('Hojas de ruta')
        ->and($libro->getSheet(1)->getTitle())->toBe('Documentos adjuntos');
});
