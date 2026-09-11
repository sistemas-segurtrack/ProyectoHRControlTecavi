<?php

use App\Models\HRControl\Contacto;
use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\DocRuta;
use App\Models\HRControl\Ruta;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

test('el export por hoja acota a una sola hoja de ruta', function () {
    $contacto = Contacto::factory()->create();
    $a = Ruta::factory()->create(['idruta' => 'T000801', 'placa' => 'AAA-111']);
    $b = Ruta::factory()->create(['idruta' => 'T000802', 'placa' => 'BBB-222']);
    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $a->idruta, 'orden' => 1]);
    DetalleRuta::factory()->for($contacto, 'contacto')->create(['ruta_idruta' => $b->idruta, 'orden' => 1]);

    $response = $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.exportar.pdf', ['hoja' => 'T000801']));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('T000801');
});
