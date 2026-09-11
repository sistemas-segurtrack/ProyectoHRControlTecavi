<?php

namespace Database\Factories\HRControl;

use App\Models\HRControl\Contacto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contacto>
 */
class ContactoFactory extends Factory
{
    protected $model = Contacto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'geocerca' => strtoupper(fake()->city()),
            'nombre' => fake()->name(),
            'correo' => [fake()->unique()->safeEmail()],
            'telefonos' => [fake()->numerify('9########'), fake()->numerify('9########')],
        ];
    }
}
