<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlace entre una hoja de ruta (tabla legada `ruta`) y el conductor de Wialon
 * que la creó desde la PWA. No se toca el esquema legado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pwa_rutas', function (Blueprint $table) {
            $table->id();
            $table->string('ruta_idruta', 20)->index();
            $table->foreignId('wialon_conductor_id')->constrained('wialon_conductores')->cascadeOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['wialon_conductor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pwa_rutas');
    }
};
