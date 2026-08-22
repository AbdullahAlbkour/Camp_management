<?php

namespace Database\Factories;

use App\Models\Camp;
use App\Models\Refugee;
use App\Models\SecurityReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityReport>
 */
class SecurityReportFactory extends Factory
{
    protected $model = SecurityReport::class;

    public function definition(): array
    {
        return [
            'refugee_id' => Refugee::factory(),
            'camp_id' => Camp::factory(),
            'incident_type' => $this->faker->word(),
            'severity' => 'low',
            'report_date' => now()->toDateString(),
            'description' => $this->faker->sentence(),
            'action_taken' => null,
            'reported_by' => null,
        ];
    }
}
