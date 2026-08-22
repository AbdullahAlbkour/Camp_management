<?php

namespace Database\Factories;

use App\Models\MedicalService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalService>
 */
class MedicalServiceFactory extends Factory
{
    protected $model = MedicalService::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'description' => null,
            'status' => 'active',
        ];
    }
}
