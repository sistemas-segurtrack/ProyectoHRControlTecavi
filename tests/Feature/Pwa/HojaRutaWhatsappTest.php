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

test('crear una hoja de ruta avisa por whatsapp el inicio del tramo al contacto de la geocerca inicial', function () {
    fakeWhatsappOk();
    $this->travelTo(now()->setDateTime(2026, 9, 15, 14, 30));
    Contacto::factory()->create([
        'geocerca' => 'PLANTA LIMA',
        'telefonos' => ['999512202', '958240782'],
    ]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    // La hora del mensaje es la de marcación del sistema, no la que indica el conductor.
    $this->postJson('/api/pwa/rutas', [
        'placa' => 'AAA-111',
        'geocerca' => 'PLANTA LIMA',
        'kilometraje' => '100',
        'fhRegistro' => '2026-09-15T09:00',
    ])->assertCreated();

    Http::assertSent(function (Request $r) {
        return $r->url() === config('services.whatsapp.url')
            && $r['TipoEnvio'] === 'numeros'
            && $r['Destinatarios'] === ['51999512202', '51958240782']
            && $r['Contenido'] === 'SE ESTA INICIANDO TRAMO EN PLANTA LIMA CON HOJA DE RUTA: T000001 a las: 15/09/2026 14:30';
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

test('crear con un documento condicionaFin=1 manda whatsapp de inicio y de fin del tramo', function () {
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
    Http::assertSent(fn (Request $r) => str_starts_with((string) $r['Contenido'], 'SE ESTA INICIANDO TRAMO EN PLANTA LIMA'));
    Http::assertSent(fn (Request $r) => str_starts_with((string) $r['Contenido'], 'SE HA FINALIZADO EL TRAMO EN PLANTA LIMA'));
});

test('registrar un avance que finaliza avisa el fin del tramo a los telefonos de la geocerca del avance', function () {
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

    Http::assertSent(fn (Request $r) => str_starts_with((string) $r['Contenido'], "SE HA FINALIZADO EL TRAMO EN DESTINO CON HOJA DE RUTA: {$idruta} a las: ")
        && $r['Destinatarios'] === ['51922222222']);
    // Inicio (ORIGEN) + fin (DESTINO): la parada par que finaliza la hoja
    // manda un solo aviso, no además el de tramo cerrado.
    Http::assertSentCount(2);
});

test('cerrar un tramo (parada par) avisa por whatsapp el fin del tramo a la geocerca de esa parada', function () {
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

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => str_starts_with((string) $r['Contenido'], "SE HA FINALIZADO EL TRAMO EN DESTINO CON HOJA DE RUTA: {$idruta} a las: ")
        && $r['Destinatarios'] === ['51922222222']);
});

test('abrir un tramo nuevo (parada impar) no avisa por whatsapp', function () {
    fakeWhatsappOk();
    Contacto::factory()->create(['geocerca' => 'DESTINO', 'telefonos' => ['922222222']]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'ORIGEN', 'kilometraje' => '100'])->json('data.idruta');
    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => '150'])->assertCreated();
    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => '200'])->assertCreated();

    Http::assertSentCount(1);
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
