<?php

use App\Models\WialonSTK\WialonCarreta;
use App\Models\WialonSTK\WialonConductor;
use App\Models\WialonSTK\WialonGeocerca;
use App\Models\WialonSTK\WialonUnidad;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/**
 * Simula Wialon con la forma real de contexto/plan-pwa/consumo.md.
 * Devuelve un closure para mutar las respuestas entre sincronizaciones.
 *
 * @return Closure(string, array): void
 */
function fakeWialon(): Closure
{
    config(['services.wialon.token' => 'token-de-prueba', 'services.wialon.resource' => 'TECAVI']);

    $data = [
        'conductores' => [
            'items' => [[
                'nm' => 'TECAVI',
                'id' => 400181399,
                'drvrs' => [
                    '1' => ['id' => 1, 'n' => 'FREDY OMAR TANTALEAN', 'c' => '9A000001BA0F1601', 'jp' => ['DNI' => '42703898', 'LICENCIA' => 'D42703898'], 'pwd' => '123456', 'p' => '+51948316718', 'ds' => 'CONDUCTOR GRANELERO'],
                    '2' => ['id' => 2, 'n' => 'JOSE MOLINA ARANA', 'c' => '9A000002CC1D2702', 'jp' => ['LICENCIA' => ''], 'pwd' => '1234', 'p' => '+51947731045', 'ds' => ''],
                ],
            ]],
        ],
        'unidades' => [
            'items' => [
                ['nm' => 'TEI838', 'id' => 401322397, 'cnm_km' => 301768, 'pflds' => ['1' => ['n' => 'registration_plate', 'v' => 'TEI838']]],
                ['nm' => 'TMQ983-TEST-APP-(PRUEBA)', 'id' => 999, 'cnm_km' => 109194, 'pflds' => ['1' => ['n' => 'vin', 'v' => 'XXX']]],
            ],
        ],
        'carretas' => [
            'items' => [[
                'nm' => 'TECAVI',
                'id' => 400181399,
                'trlrs' => ['1' => ['id' => 1, 'n' => 'ATT888']],
            ]],
        ],
        'geocercas' => [
            'items' => [[
                'nm' => 'TECAVI',
                'id' => 400181399,
                'zl' => [
                    '1' => ['id' => 1, 'n' => 'GRANJA PACANGUILLA IX', 'd' => 'Panamericana Norte'],
                    '2' => ['id' => 2, 'n' => 'PLANTA LIMA'],
                ],
            ]],
        ],
    ];

    $ref = (object) ['data' => $data];

    Http::fake(function (Request $request) use ($ref) {
        $body = $request->data();

        if (($body['svc'] ?? null) === 'token/login') {
            return Http::response(['eid' => 'SID-TEST']);
        }

        $params = json_decode((string) ($body['params'] ?? '{}'), true);

        return Http::response(match ($params['flags'] ?? null) {
            257 => $ref->data['conductores'],
            8396801 => $ref->data['unidades'],
            65537 => $ref->data['carretas'],
            4097 => $ref->data['geocercas'],
            default => ['items' => []],
        });
    });

    return function (string $clave, array $valor) use ($ref): void {
        $ref->data[$clave] = $valor;
    };
}

test('sincroniza conductores con código y hash de contraseña', function () {
    fakeWialon();

    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertSuccessful();

    expect(WialonConductor::count())->toBe(2);

    $fredy = WialonConductor::where('codigo', '9A000001BA0F1601')->first();
    expect($fredy)->not->toBeNull()
        ->and($fredy->nombre)->toBe('FREDY OMAR TANTALEAN')
        ->and($fredy->dni)->toBe('42703898')
        ->and($fredy->licencia)->toBe('D42703898')
        ->and(Hash::check('123456', $fredy->pwd_hash))->toBeTrue()
        ->and($fredy->activo)->toBeTrue();

    expect(WialonConductor::where('wialon_conductor_id', 2)->value('licencia'))->toBeNull();
});

test('sincroniza unidades con placa y contador de kilometraje', function () {
    fakeWialon();

    $this->artisan('wialon:sync', ['--solo' => 'unidades'])->assertSuccessful();

    // Unidad con placa.
    $tei = WialonUnidad::firstWhere('placa', 'TEI838');
    expect($tei)->not->toBeNull()
        ->and($tei->contador_kilometraje_km)->toBe(301768);

    // La unidad de pruebas no tiene registration_plate pero igual se guarda (placa null).
    $test = WialonUnidad::firstWhere('nombre', 'TMQ983-TEST-APP-(PRUEBA)');
    expect($test)->not->toBeNull()
        ->and($test->placa)->toBeNull()
        ->and($test->contador_kilometraje_km)->toBe(109194);

    // El combo de la PWA solo ofrece placas reales.
    expect(WialonUnidad::whereNotNull('placa')->pluck('placa')->all())->toBe(['TEI838']);
});

test('extrae la carreta de trlrs del recurso configurado', function () {
    fakeWialon();

    $this->artisan('wialon:sync', ['--solo' => 'carretas'])->assertSuccessful();

    expect(WialonCarreta::pluck('nombre')->all())->toBe(['ATT888']);
});

test('extrae las geocercas de zl (solo el nombre)', function () {
    fakeWialon();

    $this->artisan('wialon:sync', ['--solo' => 'geocercas'])->assertSuccessful();

    expect(WialonGeocerca::orderBy('wialon_geocerca_id')->pluck('nombre')->all())
        ->toBe(['GRANJA PACANGUILLA IX', 'PLANTA LIMA']);
});

test('marca inactivo al conductor que ya no viene de Wialon', function () {
    $setear = fakeWialon();
    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertSuccessful();

    $setear('conductores', [
        'items' => [[
            'nm' => 'TECAVI',
            'drvrs' => ['1' => ['id' => 1, 'n' => 'FREDY OMAR TANTALEAN', 'c' => '9A000001BA0F1601', 'jp' => ['DNI' => '42703898'], 'pwd' => '123456']],
        ]],
    ]);
    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertSuccessful();

    expect(WialonConductor::where('wialon_conductor_id', 2)->value('activo'))->toBeFalsy()
        ->and(WialonConductor::where('wialon_conductor_id', 1)->value('activo'))->toBeTruthy();
});

test('no re-hashea si la contraseña no cambió', function () {
    fakeWialon();
    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertSuccessful();
    $hash1 = WialonConductor::where('wialon_conductor_id', 1)->value('pwd_hash');

    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertSuccessful();
    $hash2 = WialonConductor::where('wialon_conductor_id', 1)->value('pwd_hash');

    expect($hash2)->toBe($hash1);
});

test('un cambio de contraseña en Wialon cierra la sesión de la PWA del conductor', function () {
    $setear = fakeWialon();
    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertSuccessful();

    $fredy = WialonConductor::where('wialon_conductor_id', 1)->firstOrFail();
    $fredy->createToken('pwa');
    expect($fredy->tokens()->count())->toBe(1);

    $setear('conductores', [
        'items' => [[
            'nm' => 'TECAVI',
            'drvrs' => [
                '1' => ['id' => 1, 'n' => 'FREDY OMAR TANTALEAN', 'c' => '9A000001BA0F1601', 'jp' => ['DNI' => '42703898'], 'pwd' => 'clave-nueva'],
                '2' => ['id' => 2, 'n' => 'JOSE MOLINA ARANA', 'c' => '9A000002CC1D2702', 'jp' => ['LICENCIA' => ''], 'pwd' => '1234', 'p' => '+51947731045', 'ds' => ''],
            ],
        ]],
    ]);
    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertSuccessful();

    expect($fredy->refresh()->tokens()->count())->toBe(0);
    expect(Hash::check('clave-nueva', $fredy->pwd_hash))->toBeTrue();
});

test('sin cambio de contraseña la sesión de la PWA sigue viva', function () {
    fakeWialon();
    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertSuccessful();

    $fredy = WialonConductor::where('wialon_conductor_id', 1)->firstOrFail();
    $fredy->createToken('pwa');

    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertSuccessful();

    expect($fredy->refresh()->tokens()->count())->toBe(1);
});

test('al desactivarse un conductor que ya no viene de Wialon se le cierra la sesión', function () {
    $setear = fakeWialon();
    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertSuccessful();

    $jose = WialonConductor::where('wialon_conductor_id', 2)->firstOrFail();
    $jose->createToken('pwa');

    $setear('conductores', [
        'items' => [[
            'nm' => 'TECAVI',
            'drvrs' => ['1' => ['id' => 1, 'n' => 'FREDY OMAR TANTALEAN', 'c' => '9A000001BA0F1601', 'jp' => ['DNI' => '42703898'], 'pwd' => '123456']],
        ]],
    ]);
    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertSuccessful();

    expect($jose->refresh()->activo)->toBeFalse()
        ->and($jose->tokens()->count())->toBe(0);
});

test('una unidad que ya no viene de Wialon se elimina del catálogo', function () {
    $setear = fakeWialon();
    $this->artisan('wialon:sync', ['--solo' => 'unidades'])->assertSuccessful();
    expect(WialonUnidad::count())->toBe(2);

    $setear('unidades', [
        'items' => [
            ['nm' => 'TEI838', 'id' => 401322397, 'cnm_km' => 301768, 'pflds' => ['1' => ['n' => 'registration_plate', 'v' => 'TEI838']]],
        ],
    ]);
    $this->artisan('wialon:sync', ['--solo' => 'unidades'])->assertSuccessful();

    expect(WialonUnidad::count())->toBe(1);
    expect(WialonUnidad::firstWhere('placa', 'TEI838'))->not->toBeNull();
});

test('una carreta que ya no viene de Wialon se elimina del catálogo', function () {
    $setear = fakeWialon();
    $setear('carretas', [
        'items' => [[
            'nm' => 'TECAVI',
            'id' => 400181399,
            'trlrs' => ['1' => ['id' => 1, 'n' => 'ATT888'], '2' => ['id' => 2, 'n' => 'BBB999']],
        ]],
    ]);
    $this->artisan('wialon:sync', ['--solo' => 'carretas'])->assertSuccessful();
    expect(WialonCarreta::count())->toBe(2);

    // BBB999 ya no viene -- el catálogo sigue trayendo ATT888, así que no es
    // una respuesta vacía (eso lo cubre el test de abajo).
    $setear('carretas', ['items' => [['nm' => 'TECAVI', 'id' => 400181399, 'trlrs' => ['1' => ['id' => 1, 'n' => 'ATT888']]]]]);
    $this->artisan('wialon:sync', ['--solo' => 'carretas'])->assertSuccessful();

    expect(WialonCarreta::pluck('nombre')->all())->toBe(['ATT888']);
});

test('una geocerca que ya no viene de Wialon se elimina del catálogo', function () {
    $setear = fakeWialon();
    $this->artisan('wialon:sync', ['--solo' => 'geocercas'])->assertSuccessful();
    expect(WialonGeocerca::count())->toBe(2);

    $setear('geocercas', [
        'items' => [[
            'nm' => 'TECAVI',
            'id' => 400181399,
            'zl' => ['2' => ['id' => 2, 'n' => 'PLANTA LIMA']],
        ]],
    ]);
    $this->artisan('wialon:sync', ['--solo' => 'geocercas'])->assertSuccessful();

    expect(WialonGeocerca::pluck('nombre')->all())->toBe(['PLANTA LIMA']);
});

test('una respuesta vacía de Wialon no borra el catálogo (posible fallo transitorio)', function () {
    $setear = fakeWialon();
    $this->artisan('wialon:sync', ['--solo' => 'geocercas'])->assertSuccessful();
    expect(WialonGeocerca::count())->toBe(2);

    $setear('geocercas', ['items' => []]);
    $this->artisan('wialon:sync', ['--solo' => 'geocercas'])->assertSuccessful();

    expect(WialonGeocerca::count())->toBe(2);
});

test('falla con mensaje claro si no hay token configurado', function () {
    config(['services.wialon.token' => null]);
    Http::fake();

    $this->artisan('wialon:sync', ['--solo' => 'conductores'])->assertFailed();
});
