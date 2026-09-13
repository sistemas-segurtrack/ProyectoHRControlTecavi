<?php

use Inertia\Testing\AssertableInertia as Assert;

test('la url de inertia incluye el subpath de APP_URL', function () {
    config(['app.url' => 'https://tools.segurtrack.com/hrcontrol']);

    $this->actingAs(crearAdmin())
        ->get('/modulos/rutas')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->url('/hrcontrol/modulos/rutas'));
});

test('sin subpath en APP_URL, la url de inertia no se altera', function () {
    config(['app.url' => 'https://tools.segurtrack.com']);

    $this->actingAs(crearAdmin())
        ->get('/modulos/rutas')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->url('/modulos/rutas'));
});
