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

test('un invitado ve el formulario de acceso, no el listado ni un redirect', function () {
    $this->get(route('modulos.rutas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('necesitaAcceso', true)
            ->where('rutas', [])
        );
});

test('un usuario sin rol admin ni usuario tambien ve el formulario de acceso', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('modulos.rutas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('necesitaAcceso', true));
});

test('la cuenta compartida tecavi (rol usuario) ve el listado real', function () {
    $ruta = Ruta::factory()->create(['idruta' => 'T000042', 'placa' => 'ABC-123']);
    DetalleRuta::factory()->create(['ruta_idruta' => $ruta->idruta, 'orden' => 1]);

    $this->actingAs(crearUsuarioTecavi())
        ->get(route('modulos.rutas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('necesitaAcceso', false)
            ->has('rutas', 1)
        );
});

test('la cuenta tecavi tambien puede exportar y ver contactos', function () {
    $user = crearUsuarioTecavi();

    $this->actingAs($user)->get(route('modulos.rutas.exportar.excel'))->assertOk();
    $this->actingAs($user)->get(route('modulos.rutas.exportar.pdf'))->assertOk();
    $this->actingAs($user)->get(route('modulos.contactos.index'))->assertOk();
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

test('el km y la fecha final se toman de la última parada registrada', function () {
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
            ->has('rutas', 1)
            ->where('rutas.0.km_inicial', '100')
            ->where('rutas.0.km_final', '250')
            ->where('rutas.0.fh_final', '01/01/2026 15:30')
        );
});

test('con una sola parada, el km final es el mismo que el inicial (todavía no hay otra)', function () {
    $ruta = Ruta::factory()->create();
    $contacto = Contacto::factory()->create();

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
        'kilometraje' => '100',
    ]);

    $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rutas', 1)
            ->where('rutas.0.km_inicial', '100')
            ->where('rutas.0.km_final', '100')
        );
});

test('una hoja con varios tramos es UNA sola fila (no una por tramo ni por parada)', function () {
    $ruta = Ruta::factory()->create();
    $contacto = Contacto::factory()->create();

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 1, 'kilometraje' => '100',
        'fhRegistro' => '2026-01-01 08:00:00',
    ]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 2, 'kilometraje' => '150',
        'fhRegistro' => '2026-01-01 10:00:00',
    ]);
    // Tramo 2: orden 3 (impar, sin cerrar todavía) -- es la última parada
    // registrada, así que sus datos son los que salen como "Final".
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 3, 'kilometraje' => '200',
        'fhRegistro' => '2026-01-01 12:00:00',
    ]);

    $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rutas', 1)
            ->where('rutas.0.hoja', $ruta->idruta)
            // Inicial = primera parada de toda la hoja (orden 1).
            ->where('rutas.0.km_inicial', '100')
            // Final = última parada registrada hasta ahora (orden 3), sea o
            // no el cierre de un tramo.
            ->where('rutas.0.km_final', '200')
        );
});

test('la hoja trae el itinerario por tramo (para el modal), no solo el resumen', function () {
    $ruta = Ruta::factory()->create();
    $contacto = Contacto::factory()->create();

    // Tramo 1: orden 1-2, cerrado.
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 1, 'geocerca' => 'A', 'kilometraje' => '100',
        'fhRegistro' => '2026-01-01 08:00:00',
    ]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 2, 'geocerca' => 'B', 'kilometraje' => '150',
        'fhRegistro' => '2026-01-01 09:00:00',
    ]);
    // Tramo 2: orden 3-4, cerrado.
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 3, 'geocerca' => 'C', 'kilometraje' => '200',
        'fhRegistro' => '2026-01-01 10:00:00',
    ]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 4, 'geocerca' => 'D', 'kilometraje' => '250',
        'fhRegistro' => '2026-01-01 11:00:00',
    ]);

    $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rutas', 1)
            ->has('rutas.0.tramos', 2)
            ->where('rutas.0.tramos.0.tramo', 1)
            ->where('rutas.0.tramos.0.geocerca', 'A')
            ->where('rutas.0.tramos.0.geocerca_final', 'B')
            ->where('rutas.0.tramos.0.km_inicial', '100')
            ->where('rutas.0.tramos.0.km_final', '150')
            ->where('rutas.0.tramos.1.tramo', 2)
            ->where('rutas.0.tramos.1.geocerca', 'C')
            ->where('rutas.0.tramos.1.geocerca_final', 'D')
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

test('el Estado de la fila es el de la tabla ruta, no el del tramo', function () {
    $contacto = Contacto::factory()->create();
    $activa = Ruta::factory()->create(['idruta' => 'T000501', 'estado' => Ruta::ACTIVA]);
    $finalizada = Ruta::factory()->create(['idruta' => 'T000502', 'estado' => Ruta::FINALIZADA]);

    // Los dos tramos de "activa" ya están cerrados a nivel de detalle (FI),
    // pero la hoja en sí sigue "ACTIVA" mientras no llegue el documento que
    // la cierra -- confirmado antes en esta misma sesión.
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $activa->idruta, 'orden' => 1, 'estado' => DetalleRuta::FINALIZADO,
        'fhRegistro' => '2026-01-01 08:00:00',
    ]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $activa->idruta, 'orden' => 2, 'estado' => DetalleRuta::FINALIZADO,
        'fhRegistro' => '2026-01-01 09:00:00',
    ]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $finalizada->idruta, 'orden' => 1, 'estado' => DetalleRuta::FINALIZADO,
        'fhRegistro' => '2026-01-01 10:00:00',
    ]);

    $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rutas', 2)
            ->where('rutas.0.hoja', 'T000502')
            ->where('rutas.0.estado', Ruta::FINALIZADA)
            ->where('rutas.0.estado_label', 'FINALIZADA')
            ->where('rutas.1.hoja', 'T000501')
            ->where('rutas.1.estado', Ruta::ACTIVA)
            ->where('rutas.1.estado_label', 'ACTIVA')
            ->where('metricas.enRuta', 1)
            ->where('metricas.finalizadas', 1)
        );
});

test('el filtro Estado busca por ruta.estado', function () {
    $contacto = Contacto::factory()->create();
    $activa = Ruta::factory()->create(['estado' => Ruta::ACTIVA]);
    $finalizada = Ruta::factory()->create(['estado' => Ruta::FINALIZADA]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $activa->idruta, 'orden' => 1]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $finalizada->idruta, 'orden' => 1]);

    $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.index', ['estado' => Ruta::FINALIZADA]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rutas', 1)
            ->where('rutas.0.hoja', $finalizada->idruta)
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
        'observacion' => 'Salida con retraso por tráfico',
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
            ->has('rutas', 1)
            ->where('rutas.0.geocerca', 'PLANTA A')
            ->where('rutas.0.geocerca_final', 'PLANTA B')
            ->where('rutas.0.cond_inicial', '01/01/2026 08:00')
            ->where('rutas.0.sis_inicial', '01/01/2026 08:20')
            ->where('rutas.0.dif_inicial.texto', '+20m')
            ->where('rutas.0.dif_inicial.signo', 'pos')
            ->where('rutas.0.dif_final.signo', 'neg')
            ->has('rutas.0.documentos', 1)
            ->where('rutas.0.documentos.0.documento', 'GRE-001')
            // La observación es la de la PARADA donde se registró el
            // documento (detalleruta.observacion), no un campo propio de
            // docruta -- se muestra junto al documento en el modal.
            ->where('rutas.0.documentos.0.observacion', 'Salida con retraso por tráfico')
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
        'resumen' => $hojas->resumenDeRuta($filtros['hoja']),
        'columnasItinerario' => ConsultaHojasRuta::COLUMNAS_FORMULARIO,
        'itinerario' => $filas->map(fn (array $f) => $hojas->filaFormulario($f))->all(),
        'documentos' => [],
    ])->render();

    expect($html)->toContain('HOJA DE RUTA', 'PILOTO UNO', 'GEOCERCA UNO')
        ->not->toContain('PILOTO DOS', 'GEOCERCA DOS');
});

test('el formulario PDF trae Fecha Inicio - Fecha Final, Estado y Observación', function () {
    $contacto = Contacto::factory()->create();
    $ruta = Ruta::factory()->create([
        'idruta' => 'T000921',
        'estado' => Ruta::ACTIVA,
    ]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
        'observacion' => 'Camino en mal estado',
        'fhRegistro' => '2026-02-01 09:00:00',
    ]);

    $hojas = app(ConsultaHojasRuta::class);
    $filtros = $hojas->filtros(Request::create('/', 'GET', ['hoja' => 'T000921']));
    $filas = $hojas->filas($filtros);

    $html = view('exports.hoja-ruta-formulario', [
        'idHoja' => $filtros['hoja'],
        'logo' => null,
        'primera' => $filas->first() ?? [],
        'resumen' => $hojas->resumenDeRuta($filtros['hoja']),
        'columnasItinerario' => ConsultaHojasRuta::COLUMNAS_FORMULARIO,
        'itinerario' => $filas->map(fn (array $f) => $hojas->filaFormulario($f))->all(),
        'documentos' => [],
    ])->render();

    expect($html)->toContain(
        'FECHA INICIO - FECHA FINAL:',
        '01/02/2026 09:00',
        'ESTADO:',
        'ACTIVA',
        'Observación',
        'Camino en mal estado',
    );
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
        'observacion' => 'Nota del tramo',
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
        // Fila 4: Conductor / Copiloto / Estado (3 campos en la primera fila).
        ->and($hoja->getCell('B4')->getValue())->toBe('CARLOS FORMULARIO')
        ->and($hoja->getCell('F4')->getValue())->toBe('ANA COPILOTO')
        // Fila 5: Precintos / Fecha Inicio - Fecha Final (2 campos).
        ->and($hoja->getCell('B5')->getValue())->toBe('PRE-9')
        // Fila 6: Placa / Carreta (2 campos).
        ->and($hoja->getCell('B6')->getValue())->toBe('FRM-001')
        ->and($hoja->getCell('F6')->getValue())->toBe('CARR-01')
        // Cabecera del itinerario en la fila 8 (encabezado: filas 1-6, fila 7 en blanco).
        // Orden: todo lo "inicial" primero, luego todo lo "final"; dentro de
        // cada bloque, Sistema antes que Conductor, y Km antes que Diferencia.
        ->and($hoja->getCell('A8')->getValue())->toBe('Conductor')
        ->and($hoja->getCell('B8')->getValue())->toBe('Geocerca Inicial')
        ->and($hoja->getCell('D8')->getValue())->toBe('Fecha Inicial Sistema')
        ->and($hoja->getCell('E8')->getValue())->toBe('Fecha Inicial Conductor')
        ->and($hoja->getCell('F8')->getValue())->toBe('Km Inicial')
        ->and($hoja->getCell('G8')->getValue())->toBe('Diferencia Inicial')
        ->and($hoja->getCell('H8')->getValue())->toBe('Geocerca Final')
        ->and($hoja->getCell('L8')->getValue())->toBe('Km Final')
        ->and($hoja->getCell('M8')->getValue())->toBe('Diferencia Final')
        // Itinerario en orden (parada 1 primero), fila 9.
        ->and($hoja->getCell('B9')->getValue())->toBe('PLANTA A')
        ->and($hoja->getCell('H9')->getValue())->toBe('PLANTA B')
        ->and((string) $hoja->getCell('F9')->getValue())->toBe('100')
        ->and((string) $hoja->getCell('L9')->getValue())->toBe('250');

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
    expect($texto)->toContain('DOCUMENTOS ADJUNTOS', 'Documento', 'GRE-900', 'Cemento', 'Ver Archivo', 'Nota del tramo');

    // La celda "Archivo" es un hipervínculo, no la URL como texto plano
    // (encabezado: filas 1-6; itinerario: cabecera fila 8, un solo tramo →
    // fila 9; documentos: título fila 11, cabecera fila 12, primer documento
    // 13 — "Observación" es la columna H (la del tramo donde se registró el
    // documento) y "Archivo" pasó a ser la I).
    expect($hoja->getCell('H13')->getValue())->toBe('Nota del tramo')
        ->and($hoja->getCell('I13')->getValue())->toBe('Ver Archivo')
        ->and($hoja->getCell('I13')->getHyperlink()->getUrl())->toBe('https://tools.segurtrack.com/hrcontrol/storage/docruta/foto-900.jpg');
});

test('el export XLSX de una sola hoja trae Fecha Inicio - Fecha Final, Estado y Observación', function () {
    $contacto = Contacto::factory()->create();
    $ruta = Ruta::factory()->create([
        'idruta' => 'T000920',
        'placa' => 'EST-001',
        'estado' => Ruta::FINALIZADA,
    ]);

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
        'observacion' => 'Salida con retraso',
        'fhRegistro' => '2026-01-01 08:00:00',
        'estado' => DetalleRuta::FINALIZADO,
    ]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 2,
        'fhRegistro' => '2026-01-01 15:30:00',
        'estado' => DetalleRuta::FINALIZADO,
    ]);

    $response = $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.exportar.excel', ['hoja' => 'T000920']));

    $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($archivo, $response->streamedContent());
    $libro = (new XlsxReader)->load($archivo);
    unlink($archivo);

    $hoja = $libro->getActiveSheet();
    // Fila 4: Conductor / Copiloto / Estado (3 en la primera fila).
    expect($hoja->getCell('H4')->getValue())->toBe('ESTADO:')
        ->and($hoja->getCell('I4')->getValue())->toBe('FINALIZADA')
        // Fila 5: Precintos / Fecha Inicio - Fecha Final.
        ->and($hoja->getCell('E5')->getValue())->toBe('FECHA INICIO - FECHA FINAL:')
        ->and($hoja->getCell('F5')->getValue())->toBe('01/01/2026 08:00 - 01/01/2026 15:30')
        // Cabecera del itinerario en la fila 8 -- Observación antes de Estado.
        ->and($hoja->getCell('N8')->getValue())->toBe('Observación')
        ->and($hoja->getCell('O8')->getValue())->toBe('Estado')
        ->and($hoja->getCell('N9')->getValue())->toBe('Salida con retraso');
});

test('el export XLSX de una hoja acota a un solo detalle cuando se pide (tal cual el modal)', function () {
    $contacto = Contacto::factory()->create();
    $ruta = Ruta::factory()->create(['idruta' => 'T000910', 'placa' => 'DET-001']);

    // Tramo 1: paradas 1 (inicio) y 2 (fin).
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 1, 'geocerca' => 'PARADA 1', 'kilometraje' => '10',
    ]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 2, 'geocerca' => 'PARADA 2', 'kilometraje' => '20',
    ]);
    // Tramo 2: paradas 3 (inicio) y 4 (fin) — el que el modal tiene seleccionado.
    $d3 = DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 3, 'geocerca' => 'PARADA 3', 'kilometraje' => '30',
    ]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta, 'orden' => 4, 'geocerca' => 'PARADA 4', 'kilometraje' => '40',
    ]);

    $response = $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.exportar.excel', ['hoja' => 'T000910', 'detalle' => $d3->iddetalleRuta]));

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

    expect($texto)->toContain('PARADA 3', 'PARADA 4')
        ->and($texto)->not->toContain('PARADA 1')
        ->and($texto)->not->toContain('PARADA 2');
});

test('el export XLSX sin hoja puntual sigue usando el listado plano de siempre', function () {
    $contacto = Contacto::factory()->create();
    $ruta = Ruta::factory()->create(['idruta' => 'T000901', 'placa' => 'LST-001']);
    $detalle = DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
        'observacion' => 'Observación del listado',
    ]);
    DocRuta::create([
        'detalleRuta_iddetalleRuta' => $detalle->iddetalleRuta,
        'tipoDocumento_idtipoDocumento' => 1,
        'documento' => 'GRE-901',
    ]);

    $response = $this->actingAs(crearAdmin())->get(route('modulos.rutas.exportar.excel'));

    $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($archivo, $response->streamedContent());
    $libro = (new XlsxReader)->load($archivo);
    unlink($archivo);

    expect($libro->getSheetCount())->toBe(2)
        ->and($libro->getSheet(0)->getTitle())->toBe('Hojas de ruta')
        ->and($libro->getSheet(1)->getTitle())->toBe('Documentos adjuntos')
        // Cabecera: ID Hoja(A) Tipo(B) Documento(C) Producto(D) Cantidad(E)
        // Envase(F) Peso Neto(G) Peso Bruto(H) Observación(I) Archivo(J).
        ->and($libro->getSheet(1)->getCell('I1')->getValue())->toBe('Observación')
        ->and($libro->getSheet(1)->getCell('I2')->getValue())->toBe('Observación del listado');
});
