<?php

use App\Pwa\Controllers\AuthController;
use App\Pwa\Controllers\RutaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PWA Conductor
|--------------------------------------------------------------------------
|
| API JSON (Sanctum) bajo /api/pwa y la SPA Vue del conductor bajo /pwa.
| Los assets estáticos (manifest, íconos) NO van en public/pwa/ para no
| chocar con la ruta /pwa/{any} (nginx respondería 403 al pedir /pwa/).
|
*/

Route::middleware('api')
    ->prefix('api/pwa')
    ->name('pwa.api.')
    ->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::get('catalogos', [AuthController::class, 'catalogosRefresh'])->name('catalogos');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');

            Route::get('rutas/activa', [RutaController::class, 'activa'])->name('rutas.activa');

            Route::post('rutas', [RutaController::class, 'store'])
                ->middleware('pwa.idempotent')->name('rutas.store');

            Route::post('rutas/{ruta}/ordenes', [RutaController::class, 'orden'])
                ->middleware('pwa.idempotent')->name('rutas.orden');
        });
    });

// Service worker servido por ruta (así el scope /pwa/ es válido, sin carpeta pública).
// El placeholder __PWA_BASE__ se reemplaza por config('pwa.base_path') — vacío
// salvo que la app vaya detrás de un proxy en subpath (ver config/pwa.php).
Route::get('/pwa/sw.js', function () {
    $contenido = str_replace(
        '__PWA_BASE__',
        config('pwa.base_path'),
        (string) file_get_contents(resource_path('pwa/sw.js')),
    );

    return response($contenido, 200, [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'no-cache',
    ]);
})->name('pwa.sw');

// Manifest de instalación: se genera (no es un archivo público estático) para
// poder anteponerle el prefijo del subpath a start_url/scope/íconos.
Route::get('/manifest-conductor.webmanifest', function () {
    $base = config('pwa.base_path');

    return response()->json([
        'name' => 'Tecavi Conductor',
        'short_name' => 'Tecavi',
        'description' => 'Hojas de ruta para conductores Tecavi',
        // `start_url` debe quedar DENTRO de `scope` según el algoritmo de
        // "within scope" del spec de Web App Manifest — que compara ambos
        // como strings, así que necesitan la misma barra final. Sin la barra
        // acá, Chrome ignora por completo el `scope` declarado ("property
        // 'scope' ignored. Start url should be within scope of scope URL.",
        // visible en chrome://inspect o vía CDP Page.getAppManifest) y cae a
        // calcular el scope efectivo como el directorio padre de start_url —
        // en este caso `/hrcontrol/` en vez de `/hrcontrol/pwa/`, mucho más
        // amplio de lo que corresponde (incluiría el panel admin).
        'start_url' => "{$base}/pwa/",
        'scope' => "{$base}/pwa/",
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#f9fafb',
        'theme_color' => '#b51927',
        'lang' => 'es',
        'icons' => [
            ['src' => asset('img/conductor/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => asset('img/conductor/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => asset('img/conductor/maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ])->header('Content-Type', 'application/manifest+json')->header('Cache-Control', 'no-cache');
})->name('pwa.manifest');

// Cascarón de la SPA: cualquier ruta bajo /pwa entrega el mismo HTML.
Route::middleware('web')
    ->get('/pwa/{any?}', fn () => view('pwa.app'))
    ->where('any', '.*')
    ->name('pwa.app');
