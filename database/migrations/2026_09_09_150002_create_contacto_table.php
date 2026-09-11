<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla legada "contacto" (esquema Tecavi). Ver nota en la migración de "ruta".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contacto')) {
            return;
        }

        Schema::create('contacto', function (Blueprint $table) {
            $table->increments('idcontacto');
            $table->string('geocerca', 200)->nullable();
            $table->string('nombre', 100)->nullable();
            // Múltiples correos / teléfonos: se guardan como JSON en columnas TEXT.
            $table->text('correo')->nullable();
            $table->text('telefonos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacto');
    }
};
