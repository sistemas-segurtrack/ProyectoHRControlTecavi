<?php

use App\Models\WialonSTK\WialonUnidad;
use App\Services\Wialon\WialonService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Simula Wialon para la unidad de pruebas y captura la llamada a
 * `unit/update_mileage_counter`.
 */
function fakeWialonContador(int $idUnidad = 402427922): object
{
    config([
        'services.wialon.token' => 'token-de-prueba',
        'services.wialon.contador_km_unidades' => ['TMQ983-TEST-APP-(PRUEBA)'],
    ]);

    $capturado = (object) ['params' => null];

    Http::fake(function (Request $request) use ($idUnidad, $capturado) {
        $body = $request->data();
        $svc = $body['svc'] ?? null;
        $params = json_decode((string) ($body['params'] ?? '{}'), true);

        if ($svc === 'token/login') {
            return Http::response(['eid' => 'SID-TEST']);
        }

        if ($svc === 'core/search_items') {
            return Http::response(['items' => [[
                'nm' => 'TMQ983-TEST-APP-(PRUEBA)',
                'id' => $idUnidad,
                'cnm_km' => 109194,
                'pflds' => [],
            ]]]);
        }

        if ($svc === 'unit/update_mileage_counter') {
            $capturado->params = $params;

            return Http::response([]);
        }

        return Http::response(['error' => 4]);
    });

    return $capturado;
}

test('wialon:contador actualiza la unidad de pruebas con el valor dado', function () {
    $capturado = fakeWialonContador();
    WialonUnidad::create(['wialon_unidad_id' => 402427922, 'nombre' => 'TMQ983-TEST-APP-(PRUEBA)']);

    $this->artisan('wialon:contador', ['unidad' => 'TMQ983-TEST-APP-(PRUEBA)', 'km' => '999'])
        ->assertSuccessful();

    expect($capturado->params)->toBe(['itemId' => 402427922, 'newValue' => 999]);
    expect(WialonUnidad::find(402427922)->contador_kilometraje_km)->toBe(999);
});

test('wialon:contador rechaza una unidad fuera de la lista blanca', function () {
    fakeWialonContador();
    config(['services.wialon.contador_km_unidades' => []]);

    $this->artisan('wialon:contador', ['unidad' => 'TMQ983-TEST-APP-(PRUEBA)', 'km' => '999'])
        ->assertFailed();
});

test('wialon:contador rechaza valores no enteros o por encima del tope', function () {
    fakeWialonContador();

    $this->artisan('wialon:contador', ['unidad' => 'TMQ983-TEST-APP-(PRUEBA)', 'km' => '12.5'])
        ->assertFailed();

    $this->artisan('wialon:contador', ['unidad' => 'TMQ983-TEST-APP-(PRUEBA)', 'km' => '5000000'])
        ->assertFailed();
});

test('actualizarContadorKilometraje valida el rango', function () {
    fakeWialonContador();

    expect(fn () => app(WialonService::class)->actualizarContadorKilometraje(1, WialonService::CONTADOR_KM_MAX + 1))
        ->toThrow(InvalidArgumentException::class);
});
