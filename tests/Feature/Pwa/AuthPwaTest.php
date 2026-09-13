<?php

use App\Models\WialonSTK\WialonCarreta;
use App\Models\WialonSTK\WialonGeocerca;
use App\Models\WialonSTK\WialonUnidad;
use Laravel\Sanctum\Sanctum;

test('login válido devuelve token, conductor y catálogos', function () {
    $c = conductorPwa('9A000042', '123456');
    conductorPwa('9A000099', 'x'); // otro → aparece como copiloto
    WialonUnidad::create(['wialon_unidad_id' => 1, 'placa' => 'TEI838', 'contador_kilometraje_km' => 12345]);
    WialonCarreta::create(['wialon_carreta_id' => 1, 'recurso' => 'TECAVI', 'nombre' => 'ATT888']);
    WialonGeocerca::create(['wialon_geocerca_id' => 1, 'recurso' => 'TECAVI', 'nombre' => 'PLANTA LIMA']);

    $this->postJson('/api/pwa/login', ['codigo' => '9A000042', 'password' => '123456'])
        ->assertOk()
        ->assertJsonPath('conductor.codigo', '9A000042')
        ->assertJsonPath('conductor.nombre', 'CONDUCTOR 9A000042')
        ->assertJsonPath('ruta_activa', null)
        ->assertJsonPath('catalogos.placas', ['TEI838'])
        ->assertJsonPath('catalogos.kilometrajes.TEI838', 12345)
        ->assertJsonPath('catalogos.carretas', ['ATT888'])
        ->assertJsonPath('catalogos.geocercas', ['PLANTA LIMA'])
        ->assertJsonPath('catalogos.copilotos', ['CONDUCTOR 9A000099'])
        ->assertJsonStructure(['token']);

    expect($c->tokens()->count())->toBe(1);
});

test('login manda unidades_en_ruta con las placas que tienen un tramo abierto', function () {
    conductorPwa('9A000042', '123456');
    $otro = conductorPwa('9A000099', 'x');
    Sanctum::actingAs($otro, ['*']);
    $idruta = $this->postJson('/api/pwa/rutas', ['placa' => 'TEI838', 'geocerca' => 'PLANTA LIMA', 'kilometraje' => '100'])
        ->assertCreated()->json('data.idruta');

    $this->postJson('/api/pwa/login', ['codigo' => '9A000042', 'password' => '123456'])
        ->assertOk()
        ->assertJsonPath('catalogos.unidades_en_ruta.TEI838', $idruta);
});

test('login con contraseña incorrecta devuelve 422', function () {
    conductorPwa('9A000042', '123456');

    $this->postJson('/api/pwa/login', ['codigo' => '9A000042', 'password' => 'malo'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('codigo');
});

test('login rechaza conductor inactivo', function () {
    $c = conductorPwa('9A000042', '123456');
    $c->update(['activo' => false]);

    $this->postJson('/api/pwa/login', ['codigo' => '9A000042', 'password' => '123456'])
        ->assertStatus(422);
});

test('las rutas protegidas exigen token', function () {
    $this->getJson('/api/pwa/me')->assertUnauthorized();
    $this->getJson('/api/pwa/rutas/activa')->assertUnauthorized();
    $this->postJson('/api/pwa/rutas', [])->assertUnauthorized();
});

test('me devuelve el conductor autenticado', function () {
    $c = conductorPwa('9A000042', '123456');
    Sanctum::actingAs($c, ['*']);

    $this->getJson('/api/pwa/me')
        ->assertOk()
        ->assertJsonPath('conductor.codigo', '9A000042')
        ->assertJsonPath('ruta_activa', null);
});

test('logout revoca el token actual', function () {
    $c = conductorPwa('9A000042', '123456');
    $token = $c->createToken('pwa')->plainTextToken;

    $this->withToken($token)->postJson('/api/pwa/logout')->assertOk();

    expect($c->tokens()->count())->toBe(0);
});
