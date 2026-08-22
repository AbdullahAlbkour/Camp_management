<?php

namespace Database\Factories;

use App\Models\Camp;
use App\Models\MedicalRecord;
use App\Models\MedicalService;
use App\Models\Refugee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecord>
 */
class MedicalRecordFactory extends Factory
{
    protected $model = MedicalRecord::class;

    public function definition(): array
    {
        return [
            'refugee_id' => Refugee::factory(),
            'medical_service_id' => MedicalService::factory(),
            'camp_id' => Camp::factory(),
            'record_date' => now()->toDateString(),
            'diagnosis' => $this->faker->sentence(),
            'notes' => null,
            'needs_follow_up' => false,
            'follow_up_date' => null,
            'recorded_by' => null,
        ];
    }

    public function needingFollowUp(?string $date = null): self
    {
        return $this->state(fn () => [
            'needs_follow_up' => true,
            'follow_up_date' => $date ?? now()->addDays(7)->toDateString(),
        ]);
    }
}
