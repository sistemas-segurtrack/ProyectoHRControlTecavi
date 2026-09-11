<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copia local de las geocercas (zones library) de Wialon. Solo el nombre —
 * alimenta el combo de "Geocerca" en la PWA (pantalla Continuar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wialon_geocercas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wialon_geocerca_id');
            $table->string('recurso', 100)->nullable();
            $table->string('nombre', 200)->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['recurso', 'wialon_geocerca_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wialon_geocercas');
    }
};
