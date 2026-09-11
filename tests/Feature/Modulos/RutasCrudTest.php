<?php

use App\Models\HRControl\Contacto;
use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\Ruta;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('un usuario sin rol admin no puede entrar a la gestión', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('modulos.rutas.gestion'))
        ->assertForbidden();
});

test('el admin ve la pantalla de gestión con el próximo código', function () {
    $this->actingAs(crearAdmin())
        ->get(route('modulos.rutas.gestion'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Modulos/Rutas/Gestion')
            ->where('proximoCodigo', 'T000001')
        );
});

test('crear hojas de ruta genera códigos correlativos T000001, T000002', function () {
    $admin = crearAdmin();

    $this->actingAs($admin)->post(route('modulos.rutas.gestion.store'), [
        'placa' => 'ABC-123',
        'piloto' => 'Juan Perez',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('modulos.rutas.gestion.store'), [
        'placa' => 'DEF-456',
        'piloto' => 'Ana Lopez',
    ])->assertSessionHasNoErrors();

    expect(Ruta::orderBy('idruta')->pluck('idruta')->all())
        ->toBe(['T000001', 'T000002']);
});

test('placa y conductor son obligatorios al crear la hoja', function () {
    $this->actingAs(crearAdmin())
        ->from(route('modulos.rutas.gestion'))
        ->post(route('modulos.rutas.gestion.store'), ['placa' => '', 'piloto' => ''])
        ->assertSessionHasErrors(['placa', 'piloto']);
});

test('registrar órdenes aplica la transición EN RUTA → FINALIZADO vía HTTP', function () {
    $admin = crearAdmin();
    $ruta = Ruta::factory()->create();
    $contacto = Contacto::factory()->create();

    $this->actingAs($admin)->post(route('modulos.rutas.gestion.detalles.store', $ruta->idruta), [
        'contacto_idcontacto' => $contacto->idcontacto,
        'kilometraje' => '100',
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('modulos.rutas.gestion.detalles.store', $ruta->idruta), [
        'contacto_idcontacto' => $contacto->idcontacto,
        'kilometraje' => '250',
    ])->assertSessionHasNoErrors();

    $ordenes = DetalleRuta::where('ruta_idruta', $ruta->idruta)->orderBy('orden')->get();

    expect($ordenes)->toHaveCount(2)
        ->and($ordenes[0]->orden)->toBe(1)
        ->and($ordenes[0]->estado)->toBe(DetalleRuta::FINALIZADO)
        ->and($ordenes[1]->orden)->toBe(2)
        ->and($ordenes[1]->estado)->toBe(DetalleRuta::EN_RUTA);
});

test('el contacto del orden debe existir', function () {
    $ruta = Ruta::factory()->create();

    $this->actingAs(crearAdmin())
        ->from(route('modulos.rutas.gestion'))
        ->post(route('modulos.rutas.gestion.detalles.store', $ruta->idruta), [
            'contacto_idcontacto' => 999,
        ])
        ->assertSessionHasErrors('contacto_idcontacto');
});

test('el admin puede eliminar un orden', function () {
    $ruta = Ruta::factory()->create();
    $contacto = Contacto::factory()->create();
    $detalle = DetalleRuta::factory()->for($contacto, 'contacto')->create([
        'ruta_idruta' => $ruta->idruta,
        'orden' => 1,
    ]);

    $this->actingAs(crearAdmin())
        ->delete(route('modulos.rutas.gestion.detalles.destroy', $detalle->iddetalleRuta))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('detalleruta', ['iddetalleRuta' => $detalle->iddetalleRuta]);
});

test('eliminar una hoja de ruta borra también sus órdenes', function () {
    $ruta = Ruta::factory()->create();
    $contacto = Contacto::factory()->create();
    DetalleRuta::factory()->for($contacto, 'contacto')->count(2)->sequence(
        ['orden' => 1],
        ['orden' => 2],
    )->create(['ruta_idruta' => $ruta->idruta]);

    $this->actingAs(crearAdmin())
        ->delete(route('modulos.rutas.gestion.destroy', $ruta->idruta))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('ruta', ['idruta' => $ruta->idruta]);
    $this->assertDatabaseMissing('detalleruta', ['ruta_idruta' => $ruta->idruta]);
});
