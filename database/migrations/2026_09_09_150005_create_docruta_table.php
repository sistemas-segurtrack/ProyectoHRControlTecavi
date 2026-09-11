<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla legada "docruta" (esquema Tecavi). Ver nota en la migración de "ruta".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('docruta')) {
            return;
        }

        Schema::create('docruta', function (Blueprint $table) {
            $table->increments('iddocRuta');
            $table->unsignedInteger('detalleRuta_iddetalleRuta');
            $table->unsignedInteger('tipoDocumento_idtipoDocumento');
            $table->string('documento', 50)->nullable();
            $table->string('cantidad', 50)->nullable();
            $table->string('producto', 50)->nullable();
            $table->string('envase', 50)->nullable();
            $table->string('pesoNeto', 50)->nullable();
            $table->string('pesoBruto', 50)->nullable();
            $table->string('imagen', 300)->nullable();

            $table->index('detalleRuta_iddetalleRuta', 'fk_docruta_detalleruta1_idx');
            $table->index('tipoDocumento_idtipoDocumento', 'fk_docruta_tipodocumento1_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docruta');
    }
};
