<?php

namespace Database\Factories;

use App\Models\Camp;
use App\Models\Checkpoint;
use App\Models\EntryExitLog;
use App\Models\Refugee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryExitLog>
 */
class EntryExitLogFactory extends Factory
{
    protected $model = EntryExitLog::class;

    public function definition(): array
    {
        return [
            'refugee_id' => Refugee::factory(),
            'camp_id' => Camp::factory(),
            'checkpoint_id' => Checkpoint::factory(),
            'movement_type' => $this->faker->randomElement(['entry', 'exit']),
            'movement_datetime' => now(),
            'reason' => null,
            'recorded_by' => null,
        ];
    }
}
