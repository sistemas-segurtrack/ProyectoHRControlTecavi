<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El login del conductor pasa a ser por `codigo` (campo `c` de Wialon), no por DNI.
 * - `wialon_conductores.wialon_id` → `wialon_conductor_id` (consistente con las demás tablas).
 * - Nueva columna `codigo` (se llena en el próximo `wialon:sync`).
 * Guardado por columna: en instalación limpia la migración original ya lo crea así.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wialon_conductores')) {
            return;
        }

        if (Schema::hasColumn('wialon_conductores', 'wialon_id')) {
            Schema::table('wialon_conductores', function (Blueprint $table) {
                $table->renameColumn('wialon_id', 'wialon_conductor_id');
            });
        }

        if (! Schema::hasColumn('wialon_conductores', 'codigo')) {
            Schema::table('wialon_conductores', function (Blueprint $table) {
                $table->string('codigo', 40)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Sin reversa: tabla de caché de Wialon.
    }
};
