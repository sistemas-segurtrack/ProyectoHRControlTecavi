<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `wialon_unidades` guarda el contador de kilometraje de Wialon (`cnm_km`) y admite
 * unidades sin placa (p. ej. la unidad de pruebas). Guardado por columna → no-op
 * en instalación limpia (la migración original ya lo crea así).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wialon_unidades')) {
            return;
        }

        if (! Schema::hasColumn('wialon_unidades', 'contador_kilometraje_km')) {
            Schema::table('wialon_unidades', function (Blueprint $table) {
                $table->unsignedInteger('contador_kilometraje_km')->nullable()->after('nombre');
            });
        }

        // Hacer `placa` nullable (había unidades sin `registration_plate`).
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('wialon_unidades', function (Blueprint $table) {
                $table->string('placa', 20)->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE wialon_unidades MODIFY placa VARCHAR(20) NULL');
        }
    }

    public function down(): void
    {
        // Sin reversa: tabla de caché de Wialon.
    }
};
