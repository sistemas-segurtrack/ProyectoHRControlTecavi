<?php

use App\Models\HRControl\Contacto;
use App\Models\User;
use App\Models\WialonSTK\WialonGeocerca;
use Inertia\Testing\AssertableInertia as Assert;

test('un usuario sin rol admin no puede entrar al CRUD', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('modulos.contactos.index'))
        ->assertForbidden();
});

test('el admin ve el listado de contactos', function () {
    Contacto::factory()->count(3)->create();

    $this->actingAs(crearAdmin())
        ->get(route('modulos.contactos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Modulos/Contactos/Index')
            ->has('contactos', 3)
        );
});

test('el listado manda las geocercas reales de Tecavi para el combo del formulario', function () {
    WialonGeocerca::create(['wialon_geocerca_id' => 1, 'recurso' => 'TECAVI', 'nombre' => 'PLANTA LIMA']);
    WialonGeocerca::create(['wialon_geocerca_id' => 2, 'recurso' => 'TECAVI', 'nombre' => 'GRANJA 675']);

    $this->actingAs(crearAdmin())
        ->get(route('modulos.contactos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('opciones.geocercas', ['GRANJA 675', 'PLANTA LIMA'])
        );
});

test('el admin puede crear un contacto con varios correos y teléfonos', function () {
    $this->actingAs(crearAdmin())
        ->post(route('modulos.contactos.store'), [
            'geocerca' => 'PLANTA LIMA',
            'nombre' => 'Central de Monitoreo',
            'correo' => ['monitoreo@segurtrack.com', 'alertas@segurtrack.com', ''],
            'telefonos' => ['999888777', '  ', '944555666'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $contacto = Contacto::firstWhere('nombre', 'Central de Monitoreo');
    expect($contacto->correo)->toBe(['monitoreo@segurtrack.com', 'alertas@segurtrack.com'])
        ->and($contacto->telefonos)->toBe(['999888777', '944555666']);

    // Se guarda como JSON en la columna TEXT.
    $this->assertDatabaseHas('contacto', [
        'correo' => '["monitoreo@segurtrack.com","alertas@segurtrack.com"]',
    ]);
});

test('un correo inválido de la lista es rechazado', function () {
    $this->actingAs(crearAdmin())
        ->from(route('modulos.contactos.index'))
        ->post(route('modulos.contactos.store'), [
            'geocerca' => 'PLANTA LIMA',
            'nombre' => 'X',
            'correo' => ['ok@segurtrack.com', 'esto-no-es-correo'],
        ])
        ->assertSessionHasErrors('correo.1');
});

test('nombre y geocerca son obligatorios', function () {
    $this->actingAs(crearAdmin())
        ->from(route('modulos.contactos.index'))
        ->post(route('modulos.contactos.store'), [
            'nombre' => '',
            'geocerca' => '',
        ])
        ->assertSessionHasErrors(['nombre', 'geocerca']);
});

test('el admin puede actualizar un contacto', function () {
    $contacto = Contacto::factory()->create(['nombre' => 'Viejo Nombre']);

    $this->actingAs(crearAdmin())
        ->put(route('modulos.contactos.update', $contacto->idcontacto), [
            'geocerca' => $contacto->geocerca,
            'nombre' => 'Nombre Nuevo',
            'correo' => $contacto->correo,
            'telefonos' => $contacto->telefonos,
        ])
        ->assertSessionHasNoErrors();

    expect($contacto->refresh()->nombre)->toBe('Nombre Nuevo');
});

test('el admin puede eliminar un contacto', function () {
    $contacto = Contacto::factory()->create();

    $this->actingAs(crearAdmin())
        ->delete(route('modulos.contactos.destroy', $contacto->idcontacto))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('contacto', ['idcontacto' => $contacto->idcontacto]);
});
