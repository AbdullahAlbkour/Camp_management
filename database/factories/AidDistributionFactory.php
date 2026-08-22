<?php

namespace Database\Factories;

use App\Models\AidDistribution;
use App\Models\AidType;
use App\Models\Camp;
use App\Models\Refugee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AidDistribution>
 */
class AidDistributionFactory extends Factory
{
    protected $model = AidDistribution::class;

    public function definition(): array
    {
        return [
            'aid_type_id' => AidType::factory(),
            'refugee_id' => Refugee::factory(),
            'household_id' => null,
            'camp_id' => Camp::factory(),
            'quantity' => $this->faker->numberBetween(1, 20),
            'distribution_date' => now()->toDateString(),
            'distributed_by' => null,
            'notes' => null,
        ];
    }
}
