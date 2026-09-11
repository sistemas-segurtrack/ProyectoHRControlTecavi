<?php

use App\Http\Controllers\Backend\Web\Modulos\Contactos\ContactosController;
use App\Http\Controllers\Backend\Web\Modulos\Rutas\Exportar\ExportarExcel;
use App\Http\Controllers\Backend\Web\Modulos\Rutas\Exportar\ExportarPdf;
use App\Http\Controllers\Backend\Web\Modulos\Rutas\RutasController;
use App\Http\Controllers\Backend\Web\Modulos\Rutas\RutasCrudController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('modulos')
    ->name('modulos.')
    ->group(function () {
        Route::get('rutas', [RutasController::class, 'index'])->name('rutas.index');
        Route::get('rutas/exportar/excel', ExportarExcel::class)->name('rutas.exportar.excel');
        Route::get('rutas/exportar/pdf', ExportarPdf::class)->name('rutas.exportar.pdf');

        // CRUD de apoyo para crear/probar hojas de ruta y sus órdenes.
        Route::get('rutas/gestion', [RutasCrudController::class, 'gestion'])->name('rutas.gestion');
        Route::post('rutas/gestion', [RutasCrudController::class, 'store'])->name('rutas.gestion.store');
        Route::put('rutas/gestion/{ruta}', [RutasCrudController::class, 'update'])->name('rutas.gestion.update');
        Route::delete('rutas/gestion/{ruta}', [RutasCrudController::class, 'destroy'])->name('rutas.gestion.destroy');
        Route::post('rutas/gestion/{ruta}/detalles', [RutasCrudController::class, 'guardarDetalle'])
            ->name('rutas.gestion.detalles.store');
        Route::delete('rutas/gestion/detalles/{detalle}', [RutasCrudController::class, 'eliminarDetalle'])
            ->name('rutas.gestion.detalles.destroy');

        Route::resource('contactos', ContactosController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });

require __DIR__.'/settings.php';
