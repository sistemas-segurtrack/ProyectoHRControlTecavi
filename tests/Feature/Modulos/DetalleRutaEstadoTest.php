<?php

use App\Models\HRControl\Contacto;
use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\Ruta;

test('el primer orden de una ruta nace EN RUTA', function () {
    $ruta = Ruta::factory()->create();
    $contacto = Contacto::factory()->create();

    $detalle = DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
    ]);

    expect($detalle->estado)->toBe(DetalleRuta::EN_RUTA);
});

test('registrar otro orden finaliza el orden anterior y deja el nuevo EN RUTA', function () {
    $ruta = Ruta::factory()->create();
    $contacto = Contacto::factory()->create();

    $primero = DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
    ]);

    $segundo = DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 2,
    ]);

    expect($primero->refresh()->estado)->toBe(DetalleRuta::FINALIZADO)
        ->and($segundo->refresh()->estado)->toBe(DetalleRuta::EN_RUTA);
});

test('el cambio de estado no afecta a otras rutas', function () {
    $contacto = Contacto::factory()->create();
    $rutaA = Ruta::factory()->create();
    $rutaB = Ruta::factory()->create();

    $detalleA = DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $rutaA->idruta,
        'orden' => 1,
    ]);

    DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $rutaB->idruta,
        'orden' => 1,
    ]);

    expect($detalleA->refresh()->estado)->toBe(DetalleRuta::EN_RUTA);
});
