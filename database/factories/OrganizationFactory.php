<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'contact_name' => $this->faker->name(),
            'phone' => $this->faker->numerify('09########'),
            'email' => $this->faker->unique()->safeEmail(),
            'status' => 'active',
            'notes' => null,
        ];
    }
}
