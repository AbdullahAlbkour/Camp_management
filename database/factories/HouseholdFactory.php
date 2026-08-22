<?php

namespace Database\Factories;

use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    public function definition(): array
    {
        return [
            'household_code' => $this->faker->unique()->bothify('HH-####'),
            'head_of_household_id' => null,
            'status' => 'active',
            'notes' => null,
        ];
    }
}
