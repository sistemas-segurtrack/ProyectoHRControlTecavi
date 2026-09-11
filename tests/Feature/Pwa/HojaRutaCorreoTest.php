<?php

use App\Mail\HojaRutaCreadaMail;
use App\Mail\HojaRutaFinalizadaMail;
use App\Models\HRControl\Contacto;
use App\Models\HRControl\TipoDocumento;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

test('crear una hoja de ruta avisa por correo al contacto de la geocerca inicial', function () {
    Mail::fake();
    Contacto::factory()->create([
        'geocerca' => 'PLANTA LIMA',
        'correo' => ['a@segurtrack.com', 'b@segurtrack.com'],
    ]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA'])
        ->assertCreated();

    Mail::assertSent(HojaRutaCreadaMail::class, fn (HojaRutaCreadaMail $mail) => $mail->hasTo('a@segurtrack.com')
        && $mail->hasTo('b@segurtrack.com')
        && $mail->resumen->idruta === 'T000001');
    Mail::assertNotSent(HojaRutaFinalizadaMail::class);
});

test('sin contacto en la geocerca no se manda nada', function () {
    Mail::fake();
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'SIN CONTACTO'])
        ->assertCreated();

    Mail::assertNothingSent();
});

test('sin geocerca al crear no se manda nada', function () {
    Mail::fake();
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111'])->assertCreated();

    Mail::assertNothingSent();
});

test('crear con un documento condicionaFin=1 manda el correo de creada y el de finalizada', function () {
    Mail::fake();
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'correo' => ['ops@segurtrack.com']]);
    $tipo = TipoDocumento::create(['nombre' => 'GUIA', 'condicionaFin' => '1']);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->post('/api/pwa/rutas', [
        'placa' => 'AAA-111',
        'geocerca' => 'PLANTA LIMA',
        'adjuntar' => '1',
        'documento' => ['tipo_documento_id' => $tipo->idtipoDocumento, 'documento' => 'GR-1'],
    ])->assertCreated();

    Mail::assertSent(HojaRutaCreadaMail::class);
    Mail::assertSent(HojaRutaFinalizadaMail::class, fn (HojaRutaFinalizadaMail $mail) => $mail->hasTo('ops@segurtrack.com')
        && $mail->resumen->documentos[0]['documento'] === 'GR-1');
});

test('registrar un avance que finaliza avisa a la geocerca del avance, no la inicial', function () {
    Mail::fake();
    Contacto::factory()->create(['geocerca' => 'ORIGEN', 'correo' => ['origen@segurtrack.com']]);
    Contacto::factory()->create(['geocerca' => 'DESTINO', 'correo' => ['destino@segurtrack.com']]);
    $tipo = TipoDocumento::create(['nombre' => 'GUIA', 'condicionaFin' => '1']);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'ORIGEN'])
        ->json('data.idruta');

    Mail::assertNotSent(HojaRutaFinalizadaMail::class);

    $this->post("/api/pwa/rutas/{$idruta}/ordenes", [
        'geocerca' => 'DESTINO',
        'adjuntar' => '1',
        'documento' => ['tipo_documento_id' => $tipo->idtipoDocumento],
    ])->assertCreated();

    Mail::assertSent(
        HojaRutaFinalizadaMail::class,
        fn (HojaRutaFinalizadaMail $mail) => $mail->hasTo('destino@segurtrack.com') && ! $mail->hasTo('origen@segurtrack.com'),
    );
});

test('registrar un avance sin condicionaFin no manda el correo de finalizada', function () {
    Mail::fake();
    Contacto::factory()->create(['geocerca' => 'DESTINO', 'correo' => ['destino@segurtrack.com']]);
    $tipo = TipoDocumento::create(['nombre' => 'PACKING', 'condicionaFin' => '0']);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111'])->json('data.idruta');

    $this->post("/api/pwa/rutas/{$idruta}/ordenes", [
        'geocerca' => 'DESTINO',
        'adjuntar' => '1',
        'documento' => ['tipo_documento_id' => $tipo->idtipoDocumento],
    ])->assertCreated();

    Mail::assertNotSent(HojaRutaFinalizadaMail::class);
});

test('el reintento con el mismo Idempotency-Key no duplica el correo', function () {
    Mail::fake();
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'correo' => ['a@segurtrack.com']]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $datos = ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA'];
    $this->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/pwa/rutas', $datos)->assertCreated();
    $this->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/pwa/rutas', $datos)->assertCreated();

    Mail::assertSent(HojaRutaCreadaMail::class, 1);
});

test('los destinatarios de varios contactos con la misma geocerca no se repiten', function () {
    Mail::fake();
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'correo' => ['a@segurtrack.com']]);
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'correo' => ['a@segurtrack.com', 'b@segurtrack.com']]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA'])->assertCreated();

    Mail::assertSent(HojaRutaCreadaMail::class, function (HojaRutaCreadaMail $mail) {
        $destinatarios = collect($mail->to)->pluck('address')->all();

        return $destinatarios === ['a@segurtrack.com', 'b@segurtrack.com'];
    });
});
