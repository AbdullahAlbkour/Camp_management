<?php

namespace Database\Factories;

use App\Models\Camp;
use App\Models\Refugee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refugee>
 */
class RefugeeFactory extends Factory
{
    protected $model = Refugee::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'father_name' => $this->faker->firstName('male'),
            'last_name' => $this->faker->lastName(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'date_of_birth' => $this->faker->dateTimeBetween('-70 years', '-1 year')->format('Y-m-d'),
            'nationality' => 'سوري',
            'document_number' => $this->faker->unique()->numerify('DOC########'),
            'phone' => $this->faker->numerify('09########'),
            'marital_status' => $this->faker->randomElement(['single', 'married']),
            'status' => 'active',
            'current_camp_id' => Camp::factory(),
            'current_shelter_id' => null,
            'housing_status' => 'unassigned',
            'presence_status' => 'inside',
            'household_id' => null,
            'relation_to_head' => null,
            'notes' => null,
        ];
    }

    public function inShelter(int $shelterId, int $campId): self
    {
        return $this->state(fn () => [
            'current_camp_id' => $campId,
            'current_shelter_id' => $shelterId,
            'housing_status' => 'assigned',
        ]);
    }
}
