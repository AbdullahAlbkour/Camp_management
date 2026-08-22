<?php

namespace Database\Factories;

use App\Models\Camp;
use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidencyTransfer>
 */
class ResidencyTransferFactory extends Factory
{
    protected $model = ResidencyTransfer::class;

    public function definition(): array
    {
        return [
            'refugee_id' => Refugee::factory(),
            'from_camp_id' => null,
            'to_camp_id' => Camp::factory(),
            'from_shelter_id' => null,
            'to_shelter_id' => null,
            'transfer_type' => 'initial',
            'reason' => null,
            'transferred_by' => null,
            'transferred_at' => now(),
        ];
    }
}
