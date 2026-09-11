<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `contacto.correo` y `contacto.telefonos` pasan a TEXT y guardan un array JSON
 * (varios correos / teléfonos por contacto). Los valores sueltos que ya existan
 * se convierten a `["valor"]`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contacto')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('contacto', function (Blueprint $table) {
                $table->text('correo')->nullable()->change();
                $table->text('telefonos')->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE contacto MODIFY correo TEXT NULL');
            DB::statement('ALTER TABLE contacto MODIFY telefonos TEXT NULL');
        }

        // Envuelve los valores planos existentes en un array JSON.
        foreach (['correo', 'telefonos'] as $columna) {
            DB::table('contacto')
                ->whereNotNull($columna)
                ->where($columna, '!=', '')
                ->where($columna, 'not like', '[%')
                ->orderBy('idcontacto')
                ->each(function (object $fila) use ($columna): void {
                    /** @var string $valor */
                    $valor = $fila->{$columna};
                    $partes = array_values(array_filter(array_map(
                        'trim',
                        preg_split('/[;,\/|]+/', $valor) ?: [$valor],
                    )));

                    DB::table('contacto')
                        ->where('idcontacto', $fila->idcontacto)
                        ->update([$columna => json_encode($partes, JSON_UNESCAPED_UNICODE)]);
                });
        }
    }

    public function down(): void
    {
        // Sin reversa: cambio de formato de datos.
    }
};
