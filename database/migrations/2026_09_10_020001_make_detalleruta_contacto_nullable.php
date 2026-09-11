<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una hoja de ruta creada por el conductor desde la PWA nace sin destino
 * (contacto). El destino se define al registrar una orden posterior.
 *
 * En MySQL la tabla legada tiene un FK real a `contacto.idcontacto` (INT con
 * signo), así que se conserva ese tipo exacto al hacerlo NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('detalleruta')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('detalleruta', function ($table) {
                $table->integer('contacto_idcontacto')->nullable()->change();
            });

            return;
        }

        DB::statement('ALTER TABLE detalleruta MODIFY contacto_idcontacto INT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('detalleruta')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('detalleruta', function ($table) {
                $table->integer('contacto_idcontacto')->nullable(false)->change();
            });

            return;
        }

        DB::statement('ALTER TABLE detalleruta MODIFY contacto_idcontacto INT NOT NULL');
    }
};
