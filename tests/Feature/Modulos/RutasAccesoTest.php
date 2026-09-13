<?php

use Inertia\Testing\AssertableInertia as Assert;

test('la contraseña correcta autentica como la cuenta tecavi y entra al listado', function () {
    crearUsuarioTecavi('clave-tecavi');

    $this->post(route('modulos.rutas.acceso'), ['password' => 'clave-tecavi'])
        ->assertRedirect(route('modulos.rutas.index'));

    $this->assertAuthenticated();
    expect(auth()->user()->email)->toBe('tecavi@segurtrack.com');

    $this->get(route('modulos.rutas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('necesitaAcceso', false));
});

test('una contraseña incorrecta no autentica', function () {
    crearUsuarioTecavi('clave-tecavi');

    $this->post(route('modulos.rutas.acceso'), ['password' => 'no-es-esta'])
        ->assertSessionHasErrors('password');

    $this->assertGuest();
});

test('la contraseña es obligatoria', function () {
    $this->post(route('modulos.rutas.acceso'), [])
        ->assertSessionHasErrors('password');
});
