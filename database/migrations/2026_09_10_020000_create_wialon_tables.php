<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copia local de catálogos de Wialon (conductores, unidades/placas, carretas)
 * que alimenta los combos de la PWA Conductor y valida el login offline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wialon_conductores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wialon_conductor_id')->unique();
            // Código del conductor en Wialon (campo `c`): con él y la contraseña inicia sesión.
            $table->string('codigo', 40)->nullable()->index();
            $table->string('dni', 20)->nullable()->index();
            $table->string('licencia', 30)->nullable();
            $table->string('nombre', 150);
            $table->string('telefono', 30)->nullable();
            $table->string('descripcion', 100)->nullable();
            $table->string('pwd_hash')->nullable();
            // Huella de la contraseña cruda de Wialon: evita re-hashear en cada sync.
            $table->string('pwd_sha', 64)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wialon_unidades', function (Blueprint $table) {
            $table->unsignedBigInteger('wialon_unidad_id')->primary();
            $table->string('placa', 20)->nullable()->index();
            $table->string('nombre', 100)->nullable();
            // Contador de kilometraje (odómetro) de Wialon: `cnm_km`.
            $table->unsignedInteger('contador_kilometraje_km')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wialon_carretas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wialon_carreta_id');
            $table->string('recurso', 100)->nullable();
            $table->string('nombre', 100)->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['recurso', 'wialon_carreta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wialon_carretas');
        Schema::dropIfExists('wialon_unidades');
        Schema::dropIfExists('wialon_conductores');
    }
};
