<?php

namespace Database\Factories;

use App\Models\Camp;
use App\Models\Shelter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shelter>
 */
class ShelterFactory extends Factory
{
    protected $model = Shelter::class;

    public function definition(): array
    {
        return [
            'camp_id' => Camp::factory(),
            'code' => $this->faker->unique()->bothify('U-###'),
            'type' => $this->faker->randomElement(['tent', 'room', 'caravan']),
            'capacity' => $this->faker->numberBetween(2, 8),
            'status' => 'active',
            'notes' => null,
        ];
    }

    public function capacity(int $capacity): self
    {
        return $this->state(fn () => ['capacity' => $capacity]);
    }
}
