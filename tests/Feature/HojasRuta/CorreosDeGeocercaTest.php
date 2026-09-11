<?php

use App\Models\HRControl\Contacto;
use App\Support\HojasRuta\CorreosDeGeocerca;

test('sin geocerca no hay destinatarios', function () {
    expect(CorreosDeGeocerca::para(null))->toBe([])
        ->and(CorreosDeGeocerca::para(''))->toBe([])
        ->and(CorreosDeGeocerca::para('   '))->toBe([]);
});

test('sin contacto que coincida no hay destinatarios', function () {
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'correo' => ['a@segurtrack.com']]);

    expect(CorreosDeGeocerca::para('OTRA GEOCERCA'))->toBe([]);
});

test('recorta espacios, filtra correos inválidos y quita duplicados', function () {
    Contacto::factory()->create([
        'geocerca' => 'PLANTA LIMA',
        'correo' => ['a@segurtrack.com', 'no-es-correo', 'a@segurtrack.com', ' b@segurtrack.com '],
    ]);

    expect(CorreosDeGeocerca::para(' PLANTA LIMA '))->toBe(['a@segurtrack.com', 'b@segurtrack.com']);
});

test('junta los correos de varios contactos con la misma geocerca', function () {
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'correo' => ['a@segurtrack.com']]);
    Contacto::factory()->create(['geocerca' => 'PLANTA LIMA', 'correo' => ['b@segurtrack.com']]);

    expect(CorreosDeGeocerca::para('PLANTA LIMA'))->toBe(['a@segurtrack.com', 'b@segurtrack.com']);
});
