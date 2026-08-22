<?php

namespace Database\Factories;

use App\Models\AidType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AidType>
 */
class AidTypeFactory extends Factory
{
    protected $model = AidType::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'unit' => $this->faker->randomElement(['item', 'kg', 'box']),
            'description' => null,
            'status' => 'active',
        ];
    }
}
