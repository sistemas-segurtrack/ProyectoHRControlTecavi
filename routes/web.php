<?php

use App\Http\Controllers\Backend\Web\Modulos\Contactos\ContactosController;
use App\Http\Controllers\Backend\Web\Modulos\Rutas\Exportar\ExportarExcel;
use App\Http\Controllers\Backend\Web\Modulos\Rutas\Exportar\ExportarPdf;
use App\Http\Controllers\Backend\Web\Modulos\Rutas\RutasAccesoController;
use App\Http\Controllers\Backend\Web\Modulos\Rutas\RutasController;
use Illuminate\Support\Facades\Route;

// Herramienta interna sin registro público: la raíz manda directo al login en
// vez del scaffold "Welcome" de ejemplo. Un usuario ya autenticado que caiga
// aquí sigue de largo hacia Rutas (config('fortify.home')) porque la ruta de
// login lleva el middleware `guest`.
Route::redirect('/', '/login')->name('home');

Route::prefix('modulos')->name('modulos.')->group(function () {
    // Sin middleware `auth`: el propio listado hace de "puerta" (como
    // https://mavitours.segurtrack.com/clientes/reporte/rutas) en vez de
    // mandar a /login. `RutasController::index` decide si muestra el
    // reporte real (admin o la cuenta compartida `tecavi@segurtrack.com`,
    // rol "usuario") o el formulario de acceso (solo contraseña, sin
    // sesión). Ver `necesitaAcceso` en la respuesta Inertia.
    Route::get('rutas', [RutasController::class, 'index'])->name('rutas.index');
    Route::post('rutas/acceso', [RutasAccesoController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('rutas.acceso');

    // Exportar y Contactos también los ve/gestiona la cuenta compartida
    // "tecavi" (rol "usuario"), no solo el admin.
    // `role:` separa roles alternativos con "|" -- una coma cambiaría de guard.
    Route::middleware(['auth', 'verified', 'role:admin|usuario'])->group(function () {
        Route::get('rutas/exportar/excel', ExportarExcel::class)->name('rutas.exportar.excel');
        Route::get('rutas/exportar/pdf', ExportarPdf::class)->name('rutas.exportar.pdf');
        Route::resource('contactos', ContactosController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });
});

require __DIR__.'/settings.php';
