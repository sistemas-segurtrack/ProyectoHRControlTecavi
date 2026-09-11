<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Renombra `wialon_id` → `wialon_<entidad>_id` en los catálogos (solo aplica en
 *   una BD que ya tenía el esquema viejo; en instalación limpia las migraciones
 *   originales ya lo crean así). Los catálogos son caché de Wialon, se re-sincronizan.
 * - Ensancha `docruta.imagen` para guardar la ruta/URL del archivo en storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- wialon_unidades: sin `id` surrogate, PK = wialon_unidad_id ---
        if (Schema::hasColumn('wialon_unidades', 'id')) {
            Schema::dropIfExists('wialon_unidades');
            Schema::create('wialon_unidades', function (Blueprint $table) {
                $table->unsignedBigInteger('wialon_unidad_id')->primary();
                $table->string('placa', 20)->index();
                $table->string('nombre', 100)->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('wialon_carretas', 'wialon_id')) {
            Schema::table('wialon_carretas', function (Blueprint $table) {
                $table->renameColumn('wialon_id', 'wialon_carreta_id');
            });
        }

        if (Schema::hasColumn('wialon_geocercas', 'wialon_id')) {
            Schema::table('wialon_geocercas', function (Blueprint $table) {
                $table->renameColumn('wialon_id', 'wialon_geocerca_id');
            });
        }

        // --- docruta.imagen: guarda la ruta del archivo ---
        if (Schema::hasTable('docruta')) {
            if (DB::getDriverName() === 'sqlite') {
                Schema::table('docruta', function (Blueprint $table) {
                    $table->string('imagen', 300)->nullable()->change();
                });
            } else {
                DB::statement('ALTER TABLE docruta MODIFY imagen VARCHAR(300) NULL');
            }
        }
    }

    public function down(): void
    {
        // Sin reversa: cambios de esquema en tablas de caché.
    }
};
