<?php

use App\Models\User;
use App\Models\WialonSTK\WialonConductor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Crea un usuario con el rol "admin" (spatie) ya asignado.
 */
function crearAdmin(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::findOrCreate('admin', 'web');

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

/**
 * Crea la cuenta compartida `tecavi@segurtrack.com` (rol "usuario", ver
 * TecaviUserSeeder/RutasAccesoController) con una contraseña conocida.
 */
function crearUsuarioTecavi(string $password = 'clave-tecavi'): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::findOrCreate('usuario', 'web');

    $user = User::factory()->create([
        'email' => 'tecavi@segurtrack.com',
        'password' => Hash::make($password),
    ]);
    $user->assignRole('usuario');

    return $user;
}

/**
 * Crea un conductor de Wialon con contraseña conocida (para probar la PWA Conductor).
 * El login es por `codigo` (campo `c` de Wialon), no por DNI.
 */
function conductorPwa(string $codigo = '9A000001', string $pwd = 'clave123'): WialonConductor
{
    static $seq = 0;
    $seq++;

    return WialonConductor::create([
        'wialon_conductor_id' => 900000 + $seq,
        'codigo' => $codigo,
        'dni' => $codigo,
        'nombre' => 'CONDUCTOR '.$codigo,
        'licencia' => 'D'.$codigo,
        'telefono' => '+51900000000',
        'pwd_hash' => Hash::make($pwd),
        'pwd_sha' => hash('sha256', $pwd),
        'activo' => true,
    ]);
}
