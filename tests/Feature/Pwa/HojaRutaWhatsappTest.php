<?php

use App\Models\HRControl\Contacto;
use App\Models\HRControl\TipoDocumento;
use App\Support\HojasRuta\TelefonosDeGeocerca;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function fakeWhatsappOk(): void
{
    config(['services.whatsapp.habilitado' => true]);
    Http::fake(['services.segurtrack.com/*' => Http::response([
        'ok' => true,
        'data' => ['ok' => true, 'resultados' => []],
    ])]);
}

test('crear una hoja de ruta avisa por whatsapp al contacto de la geocerca inicial', function () {
    fakeWhatsappOk();
    Contacto::factory()->create([
        'geocerca' => 'PLANTA LIMA',
        'telefonos' => ['999512202', '958240782'],
    ]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->assertCreated();

    Http::assertSent(function (Request $r) {
        return $r->url() === config('services.whatsapp.url')
            && $r['TipoEnvio'] === 'numeros'
            && $r['Destinatarios'] === ['51999512202', '51958240782']
            && str_contains((string) $r['Contenido'], 'T000001')
            && str_contains((string) $r['Contenido'], 'creada');
    });
});

test('sin contacto en la geocerca no manda ningun whatsapp', function () {
    fakeWhatsappOk();
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'SIN CONTACTO', 'kilometraje' => '100'])
        ->assertCreated();

    Http::assertNothingSent();
});

test('sin geocerca no se puede crear la ruta (y por lo tanto no se manda nada)', function () {
    fakeWhatsappOk();
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'kilometraje' => '100'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('geocerca');

    Http::assertNothingSent();
});

test('contacto sin telefonos en la geocerca no manda ningun whatsapp', function () {
    fakeWhatsappOk();
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'telefonos' => []]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->assertCreated();

    Http::assertNothingSent();
});

test('crear con un documento condicionaFin=1 manda whatsapp de creada y de finalizada', function () {
    fakeWhatsappOk();
    Storage::fake('public');
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'telefonos' => ['999512202']]);
    $tipo = TipoDocumento::create(['nombre' => 'GUIA', 'condicionaFin' => '1']);
    Sanctum::actingAs(conductorPwa(), ['*']);

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
    ])->assertCreated();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r) => str_contains((string) $r['Contenido'], 'creada'));
    Http::assertSent(fn (Request $r) => str_contains((string) $r['Contenido'], 'finalizada'));
});

test('registrar un avance que finaliza avisa a los telefonos de la geocerca del avance', function () {
    fakeWhatsappOk();
    Storage::fake('public');
    Contacto::factory()->create(['geocerca' => 'ORIGEN', 'telefonos' => ['911111111']]);
    Contacto::factory()->create(['geocerca' => 'DESTINO', 'telefonos' => ['922222222']]);
    $tipo = TipoDocumento::create(['nombre' => 'GUIA', 'condicionaFin' => '1']);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'ORIGEN', 'kilometraje' => '100'])
        ->json('data.idruta');

    $this->post("/api/pwa/rutas/{$idruta}/ordenes", [
        'geocerca' => 'DESTINO',
        'kilometraje' => '150',
        'adjuntar' => '1',
        'documento' => [
            'tipo_documento_id' => $tipo->idtipoDocumento,
            'documento' => 'GR-2',
            'imagen' => UploadedFile::fake()->image('guia.jpg'),
        ],
    ])->assertCreated();

    Http::assertSent(fn (Request $r) => str_contains((string) $r['Contenido'], 'finalizada')
        && $r['Destinatarios'] === ['51922222222']);
});

test('registrar un avance sin condicionaFin no manda whatsapp de finalizada', function () {
    fakeWhatsappOk();
    Storage::fake('public');
    Contacto::factory()->create(['geocerca' => 'DESTINO', 'telefonos' => ['922222222']]);
    $tipo = TipoDocumento::create(['nombre' => 'PACKING', 'condicionaFin' => '0']);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'ORIGEN', 'kilometraje' => '100'])->json('data.idruta');

    $this->post("/api/pwa/rutas/{$idruta}/ordenes", [
        'geocerca' => 'DESTINO',
        'kilometraje' => '150',
        'adjuntar' => '1',
        'documento' => [
            'tipo_documento_id' => $tipo->idtipoDocumento,
            'documento' => 'PL-2',
            'imagen' => UploadedFile::fake()->image('packing.jpg'),
        ],
    ])->assertCreated();

    Http::assertNothingSent();
});

test('el reintento con el mismo Idempotency-Key no duplica el whatsapp', function () {
    fakeWhatsappOk();
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'telefonos' => ['999512202']]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $datos = ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'];
    $this->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/pwa/rutas', $datos)->assertCreated();
    $this->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/pwa/rutas', $datos)->assertCreated();

    Http::assertSentCount(1);
});

test('con WHATSAPP_HABILITADO en false no manda nada aunque haya contacto', function () {
    config(['services.whatsapp.habilitado' => false]);
    Http::fake();
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'telefonos' => ['999512202']]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->assertCreated();

    Http::assertNothingSent();
});

test('la API respondiendo error no rompe la creacion de la ruta', function () {
    config(['services.whatsapp.habilitado' => true]);
    Http::fake(['services.segurtrack.com/*' => Http::response([
        'ok' => false,
        'error' => ['code' => 'ConnectionException', 'message' => 'gateway caido'],
    ])]);
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'telefonos' => ['999512202']]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->assertCreated();

    Http::assertSentCount(1);
});

test('TelefonosDeGeocerca normaliza a 51 + 9 digitos, filtra invalidos y quita duplicados', function () {
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'telefonos' => ['999512202', '958-240-782']]);
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'telefonos' => ['999512202', '123']]);

    expect(TelefonosDeGeocerca::para('PLANTA LIMA'))
        ->toBe(['51999512202', '51958240782']);

    expect(TelefonosDeGeocerca::para(null))->toBe([]);
    expect(TelefonosDeGeocerca::para(''))->toBe([]);
    expect(TelefonosDeGeocerca::para('SIN CONTACTO'))->toBe([]);
});
