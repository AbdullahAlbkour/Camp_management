<?php

namespace Database\Factories;

use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

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

    /**
     * Housed in one of the free spaces in $slots, or left without a unit.
     *
     * `$slots` holds one entry per free space — a unit with capacity 5 appears
     * five times — and entries are consumed as they are handed out, so however
     * the coin falls a unit is never filled past its capacity. Occupancy above
     * 100% is not a cosmetic problem: the dashboard, the assistant and the
     * transfer screen all read it as a real figure.
     *
     * A camp is always set. It is required, and drawing a fresh `Camp::factory()`
     * per refugee — what the default does — would leave 200 refugees in 200
     * camps of one person each.
     *
     * @param  Collection<int, Shelter>  $slots
     * @param  Collection<int, Camp>  $camps
     */
    public function maybeHoused(Collection $slots, Collection $camps, int $chance = 75): self
    {
        return $this->state(function () use ($slots, $camps, $chance): array {
            $shelter = fake()->boolean($chance) ? $slots->shift() : null;

            return [
                'current_camp_id' => $shelter?->camp_id ?? $camps->random()->id,
                'current_shelter_id' => $shelter?->id,
                // Kept in step with the unit on purpose: the archive guard reads
                // this column, not the foreign key, so the two disagreeing would
                // make a refugee impossible to archive for no visible reason.
                'housing_status' => $shelter !== null ? 'assigned' : 'unassigned',
            ];
        });
    }

    /**
     * Attached to one of $households, or left without a family.
     *
     * @param  Collection<int, Household>  $households
     */
    public function maybeInHousehold(Collection $households, int $chance = 65): self
    {
        return $this->state(function () use ($households, $chance): array {
            $household = $households->isNotEmpty() && fake()->boolean($chance)
                ? $households->random()
                : null;

            return [
                'household_id' => $household?->id,
                'relation_to_head' => $household === null
                    ? null
                    : fake()->randomElement(['ابن', 'ابنة', 'زوجة', 'زوج', 'والد', 'والدة', 'أخ', 'أخت']),
            ];
        });
    }

    /**
     * Most people are in the camp at any moment; a few are out.
     */
    public function sometimesOutside(int $chance = 15): self
    {
        return $this->state(fn () => [
            'presence_status' => fake()->boolean($chance) ? 'outside' : 'inside',
        ]);
    }
}
