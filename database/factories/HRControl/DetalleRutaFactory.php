<?php

namespace Database\Factories\HRControl;

use App\Models\HRControl\Contacto;
use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\Ruta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetalleRuta>
 */
class DetalleRutaFactory extends Factory
{
    protected $model = DetalleRuta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruta_idruta' => Ruta::factory(),
            'contacto_idcontacto' => Contacto::factory(),
            'geocerca' => strtoupper(fake()->city()),
            'coordenada' => fake()->latitude().','.fake()->longitude(),
            'kilometraje' => (string) fake()->numberBetween(0, 500000),
            'fhRegistro' => now(),
            'fhIndicado' => now()->addHours(4),
            'orden' => 1,
            'observacion' => fake()->optional()->sentence(),
            'estado' => null,
        ];
    }

    /**
     * Fija el orden secuencial de la hoja de ruta.
     */
    public function orden(int $orden): static
    {
        return $this->state(fn (array $attributes) => ['orden' => $orden]);
    }

    /**
     * Asocia el detalle a una ruta existente.
     */
    public function paraRuta(Ruta $ruta): static
    {
        return $this->state(fn (array $attributes) => ['ruta_idruta' => $ruta->idruta]);
    }
}
