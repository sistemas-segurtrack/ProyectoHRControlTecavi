<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla legada "detalleruta" (esquema Tecavi). Ver nota en la migración de "ruta".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('detalleruta')) {
            return;
        }

        Schema::create('detalleruta', function (Blueprint $table) {
            $table->increments('iddetalleRuta');
            $table->string('ruta_idruta', 20);
            $table->unsignedInteger('contacto_idcontacto');
            $table->string('geocerca', 200)->nullable();
            $table->string('coordenada', 50)->nullable();
            $table->string('kilometraje', 10)->nullable();
            $table->dateTime('fhRegistro')->nullable();
            $table->dateTime('fhIndicado')->nullable();
            $table->integer('orden')->nullable();
            $table->string('observacion', 500)->nullable();
            $table->string('estado', 2)->nullable();

            $table->index('ruta_idruta', 'fk_detalleruta_ruta_idx');
            $table->index('contacto_idcontacto', 'fk_detalleruta_contacto1_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalleruta');
    }
};
