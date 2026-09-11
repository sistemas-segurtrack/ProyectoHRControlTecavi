<?php

namespace Database\Factories\HRControl;

use App\Models\HRControl\Ruta;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ruta>
 */
class RutaFactory extends Factory
{
    protected $model = Ruta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idruta' => strtoupper('RT-'.Str::random(8)),
            'placa' => strtoupper(fake()->bothify('???-###')),
            'piloto' => fake()->name(),
            'copiloto' => fake()->name(),
            'precintos' => (string) fake()->numberBetween(1000, 9999),
            'carreta' => strtoupper(fake()->bothify('???-###')),
            'estado' => 'A',
        ];
    }
}
