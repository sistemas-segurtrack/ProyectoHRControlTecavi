<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla legada "ruta" (esquema Tecavi). Ya existe en la base de datos MySQL, por lo
 * que la creación se omite si la tabla está presente; la definición sirve para que
 * la suite de pruebas (SQLite en memoria) disponga del esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ruta')) {
            return;
        }

        Schema::create('ruta', function (Blueprint $table) {
            $table->string('idruta', 20)->primary();
            $table->string('placa', 50)->nullable();
            $table->string('piloto', 100)->nullable();
            $table->string('copiloto', 100)->nullable();
            $table->string('precintos', 50)->nullable();
            $table->string('carreta', 45)->nullable();
            $table->string('estado', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruta');
    }
};
