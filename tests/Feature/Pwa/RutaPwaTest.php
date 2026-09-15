<?php

use App\Jobs\Wialon\ActualizarContadorKilometrajeJob;
use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\DocRuta;
use App\Models\HRControl\Ruta;
use App\Models\HRControl\TipoDocumento;
use App\Models\WialonSTK\WialonUnidad;
use App\Pwa\Models\PwaRuta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

test('rutas/activa devuelve data null cuando no hay ruta', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->getJson('/api/pwa/rutas/activa')
        ->assertOk()
        ->assertExactJson(['data' => null]);
});

test('el kilometraje es obligatorio para crear una ruta', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['kilometraje' => 'El campo kilometraje es obligatorio.']);
});

test('el kilometraje es obligatorio para registrar un avance', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->json('data.idruta');

    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['kilometraje' => 'El campo kilometraje es obligatorio.']);
});

test('el lugar es obligatorio para crear una ruta', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'kilometraje' => '100'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['geocerca' => 'El campo lugar es obligatorio.']);
});

test('el lugar es obligatorio para registrar un avance', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->json('data.idruta');

    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['kilometraje' => '150'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['geocerca' => 'El campo lugar es obligatorio.']);
});

test('crear ruta genera hoja T###### con el conductor como piloto y orden 1 EN RUTA', function () {
    $c = conductorPwa('42703898', '123456');
    Sanctum::actingAs($c, ['*']);

    $this->postJson('/api/pwa/rutas', [
        'placa' => 'TEI838',
        'copiloto' => 'JOSE MOLINA',
        'precintos' => 'P-1',
        'carreta' => 'ATT888',
        'geocerca' => 'PLANTA LIMA',
        'kilometraje' => '1000',
    ])
        ->assertCreated()
        ->assertJsonPath('data.idruta', 'T000001')
        ->assertJsonPath('data.piloto', 'CONDUCTOR 42703898')
        ->assertJsonPath('data.copiloto', 'JOSE MOLINA')
        ->assertJsonPath('data.ordenes.0.orden', 1)
        ->assertJsonPath('data.ordenes.0.estado', DetalleRuta::EN_RUTA);

    expect(PwaRuta::where('wialon_conductor_id', $c->id)->where('ruta_idruta', 'T000001')->exists())->toBeTrue();
});

test('crear ruta respeta el fhRegistro enviado por el conductor', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', [
        'placa' => 'AAA-111',
        'geocerca' => 'PLANTA LIMA',
        'kilometraje' => '100',
        'fhRegistro' => '2026-01-15T08:30',
    ])
        ->assertCreated()
        ->assertJsonPath(
            'data.ordenes.0.fh_registro',
            fn ($valor) => str_starts_with((string) $valor, '2026-01-15T08:30'),
        );
});

test('crear ruta sin fhRegistro cae en la hora actual', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->assertCreated();

    $detalle = DetalleRuta::first();
    expect($detalle->fhRegistro->diffInSeconds(now()))->toBeLessThan(5);
});

test('registrar orden respeta el fhRegistro enviado por el conductor', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->json('data.idruta');

    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", [
        'geocerca' => 'PLANTA SUR',
        'kilometraje' => '150',
        'fhRegistro' => '2026-01-16T14:00',
    ])
        ->assertCreated()
        ->assertJsonPath(
            'data.ordenes.1.fh_registro',
            fn ($valor) => str_starts_with((string) $valor, '2026-01-16T14:00'),
        );
});

test('no deja crear una segunda ruta si ya hay una en curso', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->assertCreated();
    $this->postJson('/api/pwa/rutas', ['placa' => 'BBB-222', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->assertStatus(409);
});

test('no deja crear una ruta sobre una unidad que ya tiene un tramo en curso con otro conductor', function () {
    Sanctum::actingAs(conductorPwa('11111111'), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->assertCreated()->json('data.idruta');

    Sanctum::actingAs(conductorPwa('22222222'), ['*']);
    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '500'])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'placa' => "La unidad AAA-111 ya tiene una hoja de ruta en ruta ({$idruta}). Continúala o finalízala antes de iniciar una nueva.",
        ]);
});

test('la unidad SÍ se puede elegir si su hoja quedó ACTIVA sin ningún tramo abierto', function () {
    // Parada 1 (impar, abre tramo) -> parada 2 (par, la cierra). Sin un
    // documento que finalice la hoja, `ruta.estado` sigue ACTIVA, pero
    // ningún tramo sigue abierto -- la unidad está libre para una hoja
    // nueva (a diferencia de si la parada 2 no existiera todavía).
    Sanctum::actingAs(conductorPwa('11111111'), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->assertCreated()->json('data.idruta');
    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => '150'])
        ->assertCreated();

    Sanctum::actingAs(conductorPwa('22222222'), ['*']);
    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '500'])
        ->assertCreated();
});

test('la unidad vuelve a estar libre una vez que su hoja de ruta finaliza', function () {
    Storage::fake('public');
    $tipo = TipoDocumento::create(['nombre' => 'GUIA', 'condicionaFin' => '1']);

    Sanctum::actingAs(conductorPwa('11111111'), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->assertCreated()->json('data.idruta');
    $this->post("/api/pwa/rutas/{$idruta}/ordenes", [
        'geocerca' => 'DESTINO',
        'kilometraje' => '150',
        'adjuntar' => '1',
        'documento' => [
            'tipo_documento_id' => $tipo->idtipoDocumento,
            'documento' => 'GR-1',
            'imagen' => UploadedFile::fake()->image('guia.jpg'),
        ],
    ])->assertCreated()->assertJsonPath('data.estado', Ruta::FINALIZADA);

    Sanctum::actingAs(conductorPwa('22222222'), ['*']);
    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '500'])->assertCreated();
});

test('el Idempotency-Key reproduce la respuesta sin crear otra ruta', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);

    $r1 = $this->withHeader('Idempotency-Key', 'abc-123')
        ->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->assertCreated();

    $r2 = $this->withHeader('Idempotency-Key', 'abc-123')
        ->postJson('/api/pwa/rutas', ['placa' => 'ZZZ-999', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100']);

    $r2->assertCreated()->assertHeader('Idempotency-Replayed', 'true')
        ->assertJsonPath('data.idruta', $r1->json('data.idruta'));

    expect(PwaRuta::count())->toBe(1);
});

test('registrar orden finaliza la anterior (observer vía API)', function () {
    $c = conductorPwa();
    Sanctum::actingAs($c, ['*']);

    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->json('data.idruta');

    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", [
        'geocerca' => 'PLANTA SUR',
        'kilometraje' => '250',
        'observacion' => 'Llegada',
    ])
        ->assertCreated()
        ->assertJsonPath('data.ordenes.0.estado', DetalleRuta::FINALIZADO)
        ->assertJsonPath('data.ordenes.1.orden', 2)
        ->assertJsonPath('data.ordenes.1.estado', DetalleRuta::EN_RUTA)
        ->assertJsonPath('data.ordenes.1.geocerca', 'PLANTA SUR');
});

test('un conductor no puede registrar orden en la ruta de otro', function () {
    $a = conductorPwa('11111111');
    Sanctum::actingAs($a, ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->json('data.idruta');

    Sanctum::actingAs(conductorPwa('22222222'), ['*']);
    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => '150'])
        ->assertNotFound();
});

test('crear ruta con documento adjunto lo guarda en el primer orden', function () {
    Storage::fake('public');
    $tipo = TipoDocumento::create(['nombre' => 'PACKING LIST', 'condicionaFin' => '0']);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->post('/api/pwa/rutas', [
        'placa' => 'AAA-111',
        'geocerca' => 'PLANTA LIMA',
        'kilometraje' => '100',
        'adjuntar' => '1',
        'documento' => [
            'tipo_documento_id' => $tipo->idtipoDocumento,
            'documento' => 'PL-1',
            'imagen' => UploadedFile::fake()->image('pl.jpg'),
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.ordenes.0.orden', 1)
        ->assertJsonPath('data.ordenes.0.documentos.0.documento', 'PL-1')
        ->assertJsonPath('data.estado', Ruta::ACTIVA);

    expect(DocRuta::query()->count())->toBe(1);
});

test('crear ruta con documento condicionaFin = 1 nace finalizada', function () {
    Storage::fake('public');
    $tipo = TipoDocumento::create(['nombre' => 'GUIA', 'condicionaFin' => '1']);
    $c = conductorPwa();
    Sanctum::actingAs($c, ['*']);

    $this->post('/api/pwa/rutas', [
        'placa' => 'AAA-111',
        'geocerca' => 'PLANTA LIMA',
        'kilometraje' => '100',
        'adjuntar' => '1',
        'documento' => [
            'tipo_documento_id' => $tipo->idtipoDocumento,
            'documento' => 'GR-1',
            'imagen' => UploadedFile::fake()->image('guia.jpg'),
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.estado', Ruta::FINALIZADA)
        ->assertJsonPath('data.ordenes.0.estado', DetalleRuta::FINALIZADO);

    // Sin ruta activa → el conductor puede crear otra.
    $this->getJson('/api/pwa/rutas/activa')->assertOk()->assertExactJson(['data' => null]);
});

test('registrar orden con documento adjunto guarda el DocRuta y el archivo', function () {
    Storage::fake('public');
    $tipo = TipoDocumento::create(['nombre' => 'PACKING LIST', 'condicionaFin' => '0']);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->json('data.idruta');

    $this->post("/api/pwa/rutas/{$idruta}/ordenes", [
        'geocerca' => 'PLANTA SUR',
        'kilometraje' => '150',
        'adjuntar' => '1',
        'documento' => [
            'tipo_documento_id' => $tipo->idtipoDocumento,
            'documento' => 'PL-9001',
            'cantidad' => '12',
            'producto' => 'MAIZ',
            'envase' => '3',
            'peso_neto' => '1000',
            'peso_bruto' => '1020',
            'imagen' => UploadedFile::fake()->image('guia.jpg'),
        ],
    ])->assertCreated();

    $doc = DocRuta::query()->firstOrFail();
    expect($doc->documento)->toBe('PL-9001')
        ->and($doc->pesoNeto)->toBe('1000')
        ->and($doc->tipoDocumento_idtipoDocumento)->toBe($tipo->idtipoDocumento)
        ->and($doc->imagen)->toContain('/storage/docruta/');

    Storage::disk('public')->assertExists(
        str_replace('/storage/', '', (string) parse_url((string) $doc->imagen, PHP_URL_PATH)),
    );

    // condicionaFin = 0 → la hoja sigue en curso.
    expect(Ruta::find($idruta)->estado)->toBe(Ruta::ACTIVA);
    $this->getJson('/api/pwa/rutas/activa')->assertOk()->assertJsonPath('data.idruta', $idruta);
});

test('un documento con condicionaFin = 1 finaliza la hoja de ruta', function () {
    Storage::fake('public');
    $tipo = TipoDocumento::create(['nombre' => 'GUIA DE REMISION', 'condicionaFin' => '1']);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->json('data.idruta');

    $res = $this->post("/api/pwa/rutas/{$idruta}/ordenes", [
        'geocerca' => 'DESTINO',
        'kilometraje' => '150',
        'adjuntar' => '1',
        'documento' => [
            'tipo_documento_id' => $tipo->idtipoDocumento,
            'documento' => 'GR-1',
            'imagen' => UploadedFile::fake()->image('guia.jpg'),
        ],
    ])->assertCreated();

    // Todos los órdenes quedan FINALIZADO y la hoja pasa a FINALIZADA.
    $estados = collect($res->json('data.ordenes'))->pluck('estado')->unique()->values()->all();
    expect($estados)->toBe([DetalleRuta::FINALIZADO])
        ->and($res->json('data.estado'))->toBe(Ruta::FINALIZADA);

    // Ya no hay ruta activa → la PWA vuelve al menú.
    $this->getJson('/api/pwa/rutas/activa')->assertOk()->assertExactJson(['data' => null]);
});

test('el tipo de documento es obligatorio si se activa Adjuntar', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->json('data.idruta');

    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['adjuntar' => true, 'documento' => ['documento' => 'X']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('documento.tipo_documento_id');
});

test('el peso neto debe ser numérico y el error sale en español', function () {
    $tipo = TipoDocumento::create(['nombre' => 'GUIA', 'condicionaFin' => '0']);
    Sanctum::actingAs(conductorPwa(), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->json('data.idruta');

    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", [
        'adjuntar' => true,
        'documento' => ['tipo_documento_id' => $tipo->idtipoDocumento, 'peso_neto' => 'abc'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'documento.peso_neto' => 'El campo peso neto debe ser un número.',
        ]);
});

test('login expone el catálogo de tipos de documento con su condicionaFin', function () {
    TipoDocumento::create(['nombre' => 'GUIA', 'condicionaFin' => '1']);
    TipoDocumento::create(['nombre' => 'PACKING', 'condicionaFin' => '0']);
    conductorPwa('9A000042', '123456');

    $this->postJson('/api/pwa/login', ['codigo' => '9A000042', 'password' => '123456'])
        ->assertOk()
        ->assertJsonPath('catalogos.tipos_documento.0.nombre', 'GUIA')
        ->assertJsonPath('catalogos.tipos_documento.0.condiciona_fin', true)
        ->assertJsonPath('catalogos.tipos_documento.1.condiciona_fin', false);
});

test('crear una ruta no valida el kilometraje contra el contador de Wialon', function () {
    // El contador de Wialon se sincroniza cada minuto y puede estar
    // desactualizado -- rechazar de arranque un kilometraje real por eso
    // bloqueaba al conductor sin motivo. Esa validación solo aplica dentro
    // de la misma ruta (ver "Continuar"), no al iniciar una hoja nueva.
    WialonUnidad::create([
        'wialon_unidad_id' => 555,
        'placa' => 'AAA-111',
        'nombre' => 'UNIDAD AAA',
        'contador_kilometraje_km' => 5000,
    ]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '4999'])
        ->assertCreated();
});

test('deja crear una ruta con kilometraje igual o mayor al contador de Wialon', function () {
    WialonUnidad::create([
        'wialon_unidad_id' => 555,
        'placa' => 'AAA-111',
        'nombre' => 'UNIDAD AAA',
        'contador_kilometraje_km' => 5000,
    ]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '5000'])
        ->assertCreated();
});

test('no deja registrar una parada con kilometraje igual o menor al de la parada anterior', function (string $kilometraje) {
    Sanctum::actingAs(conductorPwa(), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '300'])
        ->json('data.idruta');

    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => $kilometraje])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['kilometraje' => 'El kilometraje debe ser mayor al de la parada anterior (300 km).']);
})->with(['igual' => '300', 'menor' => '299']);

test('la parada que abre un tramo nuevo también debe superar a la parada anterior', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);
    // Tramo 1: parada 1 (300) -> parada 2 (350).
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '300'])
        ->json('data.idruta');
    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => '350'])
        ->assertCreated();

    // Parada 3 (impar): el odómetro es uno solo, se compara con la parada 2.
    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => '350'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['kilometraje' => 'El kilometraje debe ser mayor al de la parada anterior (350 km).']);
    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => '351'])
        ->assertCreated();
});

test('un avance no se compara contra el contador de Wialon, solo contra la parada anterior', function () {
    WialonUnidad::create([
        'wialon_unidad_id' => 555,
        'placa' => 'AAA-111',
        'nombre' => 'UNIDAD AAA',
        'contador_kilometraje_km' => 5000,
    ]);
    Sanctum::actingAs(conductorPwa(), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '300'])
        ->json('data.idruta');

    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => '301'])
        ->assertCreated();
});

test('si la parada anterior no tiene kilometraje (registro antiguo) no hay mínimo', function () {
    Sanctum::actingAs(conductorPwa(), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '300'])
        ->json('data.idruta');
    DetalleRuta::query()->where('ruta_idruta', $idruta)->update(['kilometraje' => null]);

    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => '1'])
        ->assertCreated();
});

test('empuja el contador a Wialon cuando el kilometraje ingresado lo supera', function () {
    Queue::fake();
    WialonUnidad::create([
        'wialon_unidad_id' => 777,
        'placa' => 'AAA-111',
        'nombre' => 'UNIDAD AAA',
        'contador_kilometraje_km' => 100,
    ]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '150'])
        ->assertCreated();

    Queue::assertPushed(
        ActualizarContadorKilometrajeJob::class,
        fn (ActualizarContadorKilometrajeJob $job) => $job->wialonUnidadId === 777 && $job->kilometraje === 150,
    );
});

test('no empuja el contador a Wialon cuando el kilometraje ingresado no lo supera', function () {
    Queue::fake();
    WialonUnidad::create([
        'wialon_unidad_id' => 777,
        'placa' => 'AAA-111',
        'nombre' => 'UNIDAD AAA',
        'contador_kilometraje_km' => 150,
    ]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '150'])
        ->assertCreated();

    Queue::assertNotPushed(ActualizarContadorKilometrajeJob::class);
});

test('adjuntar sin código de documento o sin foto falla la validación', function () {
    $tipo = TipoDocumento::create(['nombre' => 'GUIA', 'condicionaFin' => '0']);
    Sanctum::actingAs(conductorPwa(), ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->json('data.idruta');

    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", [
        'kilometraje' => '150',
        'adjuntar' => true,
        'documento' => ['tipo_documento_id' => $tipo->idtipoDocumento],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['documento.documento', 'documento.imagen']);
});
