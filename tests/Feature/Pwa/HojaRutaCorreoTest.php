<?php

use App\Mail\HojaRutaCreadaMail;
use App\Mail\HojaRutaFinalizadaMail;
use App\Mail\HojaRutaTramoCerradoMail;
use App\Models\HRControl\Contacto;
use App\Models\HRControl\TipoDocumento;
use App\Support\HojasRuta\ResumenHojaRuta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

test('crear una hoja de ruta avisa por correo al contacto de la geocerca inicial', function () {
    Mail::fake();
    Contacto::factory()->create([
        'geocerca' => 'PLANTA LIMA',
        'correo' => ['a@segurtrack.com', 'b@segurtrack.com'],
    ]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->assertCreated();

    Mail::assertSent(HojaRutaCreadaMail::class, fn (HojaRutaCreadaMail $mail) => $mail->hasTo('a@segurtrack.com')
        && $mail->hasTo('b@segurtrack.com')
        && $mail->resumen->idruta === 'T000001');
    Mail::assertNotSent(HojaRutaFinalizadaMail::class);
});

test('sin contacto en la geocerca no se manda nada', function () {
    Mail::fake();
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'SIN CONTACTO', 'kilometraje' => '100'])
        ->assertCreated();

    Mail::assertNothingSent();
});

test('sin geocerca no se puede crear la ruta (y por lo tanto no se manda nada)', function () {
    Mail::fake();
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'kilometraje' => '100'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('geocerca');

    Mail::assertNothingSent();
});

test('crear con un documento condicionaFin=1 manda el correo de creada y el de finalizada', function () {
    Mail::fake();
    Storage::fake('public');
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'correo' => ['ops@segurtrack.com']]);
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

    Mail::assertSent(HojaRutaCreadaMail::class);
    Mail::assertSent(HojaRutaFinalizadaMail::class, fn (HojaRutaFinalizadaMail $mail) => $mail->hasTo('ops@segurtrack.com')
        && $mail->resumen->documentos[0]['documento'] === 'GR-1');
});

test('registrar un avance que finaliza avisa a la geocerca del avance, no la inicial', function () {
    Mail::fake();
    Storage::fake('public');
    Contacto::factory()->create(['geocerca' => 'ORIGEN', 'correo' => ['origen@segurtrack.com']]);
    Contacto::factory()->create(['geocerca' => 'DESTINO', 'correo' => ['destino@segurtrack.com']]);
    $tipo = TipoDocumento::create(['nombre' => 'GUIA', 'condicionaFin' => '1']);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'ORIGEN', 'kilometraje' => '100'])
        ->json('data.idruta');

    Mail::assertNotSent(HojaRutaFinalizadaMail::class);

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

    Mail::assertSent(
        HojaRutaFinalizadaMail::class,
        fn (HojaRutaFinalizadaMail $mail) => $mail->hasTo('destino@segurtrack.com') && ! $mail->hasTo('origen@segurtrack.com'),
    );
    // La parada par que finaliza la hoja manda solo el aviso final.
    Mail::assertNotSent(HojaRutaTramoCerradoMail::class);
});

test('cerrar un tramo (parada par) avisa por correo a la geocerca de esa parada, sin finalizar', function () {
    Mail::fake();
    Storage::fake('public');
    Contacto::factory()->create(['geocerca' => 'DESTINO', 'correo' => ['destino@segurtrack.com']]);
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

    Mail::assertSent(HojaRutaTramoCerradoMail::class, fn (HojaRutaTramoCerradoMail $mail) => $mail->hasTo('destino@segurtrack.com')
        && $mail->resumen->idruta === $idruta
        && $mail->resumen->documentos[0]['documento'] === 'PL-2');
    Mail::assertNotSent(HojaRutaFinalizadaMail::class);
});

test('el correo de tramo cerrado muestra la hoja y los documentos de esa parada', function () {
    $resumen = new ResumenHojaRuta(
        idruta: 'T000009',
        placa: 'AAA-111',
        piloto: 'PILOTO',
        copiloto: null,
        carreta: null,
        precintos: null,
        geocerca: 'DESTINO',
        fecha: now(),
        estadoLabel: 'ACTIVA',
        documentos: [['tipo' => 'GUIA', 'documento' => 'GR-9', 'producto' => null, 'cantidad' => null, 'envase' => null, 'pesoNeto' => null, 'pesoBruto' => null]],
    );

    $mail = new HojaRutaTramoCerradoMail($resumen);

    $mail->assertHasSubject('Hoja de ruta T000009: tramo cerrado');
    $mail->assertSeeInHtml('DESTINO');
    $mail->assertSeeInHtml('GR-9');
});

test('abrir un tramo nuevo (parada impar) no avisa por correo', function () {
    Mail::fake();
    Contacto::factory()->create(['geocerca' => 'DESTINO', 'correo' => ['destino@segurtrack.com']]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'ORIGEN', 'kilometraje' => '100'])->json('data.idruta');
    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => '150'])->assertCreated();
    $this->postJson("/api/pwa/rutas/{$idruta}/ordenes", ['geocerca' => 'DESTINO', 'kilometraje' => '200'])->assertCreated();

    Mail::assertSent(HojaRutaTramoCerradoMail::class, 1);
});

test('el reintento con el mismo Idempotency-Key no duplica el correo', function () {
    Mail::fake();
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'correo' => ['a@segurtrack.com']]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $datos = ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'];
    $this->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/pwa/rutas', $datos)->assertCreated();
    $this->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/pwa/rutas', $datos)->assertCreated();

    Mail::assertSent(HojaRutaCreadaMail::class, 1);
});

test('los destinatarios de varios contactos con la misma geocerca no se repiten', function () {
    Mail::fake();
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'correo' => ['a@segurtrack.com']]);
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'correo' => ['a@segurtrack.com', 'b@segurtrack.com']]);
    Sanctum::actingAs(conductorPwa(), ['*']);

    $this->postJson('/api/pwa/rutas', ['placa' => 'AAA-111', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])->assertCreated();

    Mail::assertSent(HojaRutaCreadaMail::class, function (HojaRutaCreadaMail $mail) {
        $destinatarios = collect($mail->to)->pluck('address')->all();

        return $destinatarios === ['a@segurtrack.com', 'b@segurtrack.com'];
    });
});
