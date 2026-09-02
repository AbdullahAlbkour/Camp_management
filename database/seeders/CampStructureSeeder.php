<?php

namespace Database\Seeders;

use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use Faker\Generator;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Camp structure and a population of 200 refugees spread across it.
 *
 * Kept apart from DatabaseSeeder, which owns the roles and the demo login
 * accounts the application cannot start without: folding this in would mean
 * re-running those every time a fresh population is wanted, and losing them
 * on any change here.
 *
 * Written with Eloquent factories rather than bulk inserts. That is slower
 * than DemoLargeDatasetSeeder's approach and deliberate at this size: model
 * events fire, so `refugees.search_text` — the folded blob Arabic search
 * matches against — is built on write instead of needing a rebuild pass.
 */
class CampStructureSeeder extends Seeder
{
    private const CAMP_COUNT = 4;

    private const SHELTERS_PER_CAMP = 12;

    private const HOUSEHOLD_COUNT = 40;

    private const REFUGEE_COUNT = 200;

    /** Share of refugees given a unit; the rest are waiting for one. */
    private const HOUSED_CHANCE = 75;

    /** Share of refugees attached to a family; the rest are on their own. */
    private const FAMILY_CHANCE = 65;

    /** Marks the rows this seeder owns, so a re-run can clear only its own. */
    private const DOCUMENT_PREFIX = 'POP-';

    private const HOUSEHOLD_PREFIX = 'FAM-';

    public function run(): void
    {
        $this->guardFakerIsInstalled();

        // A fixed seed so two runs of the same code describe the same camp, and
        // a figure quoted from a screenshot still matches the database later.
        mt_srand(20260902);

        $this->clearPreviousRun();

        $camps = $this->seedCamps();
        $shelters = $this->seedShelters($camps);
        $households = collect(range(1, self::HOUSEHOLD_COUNT))->map(
            fn (int $number): Household => Household::query()->firstWhere('household_code', sprintf(self::HOUSEHOLD_PREFIX.'%04d', $number))
                ?? Household::factory()->create(['household_code' => sprintf(self::HOUSEHOLD_PREFIX.'%04d', $number)])
        );

        $refugees = $this->seedRefugees($shelters, $camps, $households);
        $this->assignHeadsOfHousehold($households);

        // Counted off the rows this run created, so the figures are not inflated
        // by whatever DatabaseSeeder or an earlier run already put in the table.
        $housed = $refugees->whereNotNull('current_shelter_id')->count();
        $inFamily = $refugees->whereNotNull('household_id')->count();

        $this->command?->info(sprintf(
            'تم إنشاء %d مخيمات، %d وحدة سكنية، %d أسرة، و%d لاجئ (%d مسكَّن، %d بلا سكن، %d ضمن أسرة، %d بلا أسرة).',
            $camps->count(),
            $shelters->count(),
            $households->count(),
            self::REFUGEE_COUNT,
            $housed,
            self::REFUGEE_COUNT - $housed,
            $inFamily,
            self::REFUGEE_COUNT - $inFamily,
        ));
    }

    /**
     * Stop with something readable when Faker is missing.
     *
     * Laravel's `Factory::withFaker()` returns null rather than throwing when
     * the package is absent, so the first `$this->faker->...` in any factory
     * fails as "Call to a member function on null" — which names neither the
     * cause nor the cure. Faker is a runtime requirement of this application
     * precisely because this seeder exists, so an install that skipped it is
     * incomplete rather than merely missing a testing tool.
     */
    private function guardFakerIsInstalled(): void
    {
        if (class_exists(Generator::class)) {
            return;
        }

        throw new RuntimeException(
            'حزمة Faker غير مثبّتة، والبذرة تعتمد على الـ Factories التي تستخدمها. '
            .'شغّل «composer install» بدون الخيار --no-dev، ثم أعد المحاولة.'
        );
    }

    /**
     * Remove what an earlier run of this seeder created.
     *
     * Needed because the run is deterministic: the same seed produces the same
     * document numbers, and appending them a second time violates the unique
     * column. Force-deleted rather than deleted because both models soft-delete
     * — a hidden row still holds its unique document number and family code,
     * and the next run would collide with something it cannot see.
     *
     * Only rows carrying this seeder's own prefixes are touched, so the demo
     * accounts and the records DatabaseSeeder owns are left alone.
     */
    private function clearPreviousRun(): void
    {
        Refugee::withTrashed()
            ->where('document_number', 'like', self::DOCUMENT_PREFIX.'%')
            ->forceDelete();

        Household::withTrashed()
            ->where('household_code', 'like', self::HOUSEHOLD_PREFIX.'%')
            ->forceDelete();
    }

    /**
     * @return Collection<int, Camp>
     */
    private function seedCamps(): Collection
    {
        return collect(['مخيم السلام', 'مخيم النور', 'مخيم الأمل', 'مخيم الرحمة'])
            ->take(self::CAMP_COUNT)
            // DatabaseSeeder already creates some of these by name, and the
            // column is unique — so an existing camp is reused rather than
            // duplicated, which also makes this seeder safe to re-run.
            ->map(fn (string $name, int $index): Camp => Camp::query()->firstWhere('name', $name)
                ?? Camp::factory()->create([
                    'name' => $name,
                    'location' => ['القطاع الشمالي', 'القطاع الشرقي', 'القطاع الغربي', 'القطاع الجنوبي'][$index],
                    'capacity' => 400 + $index * 150,
                    'status' => 'active',
                ]));
    }

    /**
     * Units belong to a camp, so they are created per camp rather than in one
     * batch — the code has to be unique inside its camp, not across the system.
     *
     * @param  Collection<int, Camp>  $camps
     * @return Collection<int, Shelter>
     */
    private function seedShelters(Collection $camps): Collection
    {
        return $camps->flatMap(fn (Camp $camp, int $campIndex): Collection => collect(range(1, self::SHELTERS_PER_CAMP))
            ->map(function (int $number) use ($camp, $campIndex): Shelter {
                $code = sprintf('%s%02d-%03d', chr(65 + $campIndex), $campIndex + 1, $number);

                return Shelter::query()->where('camp_id', $camp->id)->firstWhere('code', $code)
                    ?? Shelter::factory()->create([
                        'camp_id' => $camp->id,
                        'code' => $code,
                        'type' => ['tent', 'caravan', 'room'][$number % 3],
                        'capacity' => fake()->numberBetween(3, 8),
                        'status' => 'active',
                    ]);
            }));
    }

    /**
     * @param  Collection<int, Shelter>  $shelters
     * @param  Collection<int, Camp>  $camps
     * @param  Collection<int, Household>  $households
     * @return Collection<int, Refugee>
     */
    private function seedRefugees(Collection $shelters, Collection $camps, Collection $households): Collection
    {
        $slots = $this->freeSpaces($shelters);

        // Created one at a time so each draws its own coin flip. A single
        // ->count(200)->create() would evaluate the state closures per model
        // too, but building the slot bag first is what keeps the draws honest.
        return Refugee::factory()
            ->count(self::REFUGEE_COUNT)
            // Numbered rather than faked: faker only guarantees uniqueness
            // within one process, so a second run would regenerate numbers the
            // table already holds. The prefix is also what clearPreviousRun()
            // recognises as this seeder's own.
            ->sequence(fn (Sequence $sequence) => [
                'document_number' => sprintf(self::DOCUMENT_PREFIX.'%06d', $sequence->index + 1),
            ])
            ->maybeHoused($slots, $camps, self::HOUSED_CHANCE)
            ->maybeInHousehold($households, self::FAMILY_CHANCE)
            ->sometimesOutside()
            ->create();
    }

    /**
     * One entry per free space, shuffled.
     *
     * Handing out spaces rather than units is what stops a unit of capacity 3
     * from being given six residents, which would show as occupancy above 100%
     * on the dashboard.
     *
     * @param  Collection<int, Shelter>  $shelters
     * @return Collection<int, Shelter>
     */
    private function freeSpaces(Collection $shelters): Collection
    {
        return $shelters
            ->flatMap(fn (Shelter $shelter): array => array_fill(0, (int) $shelter->capacity, $shelter))
            ->shuffle()
            ->values();
    }

    /**
     * Every family that ended up with members gets one of them as its head.
     *
     * Left to the end because the head has to be a refugee, and the refugees do
     * not exist until after the families they are attached to.
     *
     * @param  Collection<int, Household>  $households
     */
    private function assignHeadsOfHousehold(Collection $households): void
    {
        foreach ($households as $household) {
            $head = Refugee::query()
                ->where('household_id', $household->id)
                ->orderByDesc('date_of_birth')
                ->first();

            if ($head === null) {
                continue;
            }

            $household->update(['head_of_household_id' => $head->id]);
            $head->update(['relation_to_head' => 'رب الأسرة']);
        }
    }
}
