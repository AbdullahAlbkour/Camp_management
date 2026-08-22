<?php

namespace Database\Factories;

use App\Models\Camp;
use App\Models\Checkpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Checkpoint>
 */
class CheckpointFactory extends Factory
{
    protected $model = Checkpoint::class;

    public function definition(): array
    {
        return [
            'camp_id' => Camp::factory(),
            'name' => 'بوابة '.$this->faker->unique()->numerify('##'),
            'location' => $this->faker->streetName(),
            'status' => 'active',
        ];
    }
}
