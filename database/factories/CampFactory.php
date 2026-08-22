<?php

namespace Database\Factories;

use App\Models\Camp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Camp>
 */
class CampFactory extends Factory
{
    protected $model = Camp::class;

    public function definition(): array
    {
        return [
            'name' => 'مخيم '.$this->faker->unique()->numerify('###'),
            'location' => $this->faker->city(),
            'capacity' => $this->faker->numberBetween(100, 5000),
            'status' => 'active',
            'notes' => null,
        ];
    }
}
