<?php

namespace Database\Seeders;

use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Camp structure and a population of 200 refugees spread across it.
 *
 * Kept apart from DatabaseSeeder, which owns the roles and the demo login
 * accounts the application cannot start without: folding this in would mean
 * re-running those every time a fresh population is wanted, and losing them
 * on any change here.
 *
 * Every value comes from a plain PHP array in this file. No factory and no
 * Faker: factories are a testing tool that lives in require-dev, so a seeder
 * built on them cannot run on an installation done with `--no-dev` — which is
 * the normal way to install this application on the machine that will use it.
 * `php artisan db:seed --class=CampStructureSeeder` therefore works against
 * the vendor directory as shipped.
 *
 * Nothing here is random. Which refugee is housed, which belongs to a family,
 * and which is outside the camp are all decided by arithmetic on the row's own
 * index (see quota()), so the fixture is identical on every machine and a
 * figure quoted from a screenshot still matches the database a month later.
 *
 * Written with Eloquent rather than bulk inserts, and that is deliberate at
 * this size: model events fire, so `refugees.search_text` — the folded blob
 * Arabic search matches against — is built on write instead of needing a
 * rebuild pass afterwards.
 */
class CampStructureSeeder extends Seeder
{
    private const SHELTERS_PER_CAMP = 12;

    private const HOUSEHOLD_COUNT = 40;

    private const REFUGEE_COUNT = 200;

    /** How many of the population get a unit; the rest are waiting for one. */
    private const HOUSED_COUNT = 150;

    /** How many belong to a family; the rest are registered on their own. */
    private const IN_FAMILY_COUNT = 130;

    /** How many are out of the camp at the moment the fixture is taken. */
    private const OUTSIDE_COUNT = 30;

    /** Marks the rows this seeder owns, so a re-run can clear only its own. */
    private const DOCUMENT_PREFIX = 'POP-';

    private const HOUSEHOLD_PREFIX = 'FAM-';

    /** @var list<array{name: string, location: string, capacity: int}> */
    private const CAMPS = [
        ['name' => 'مخيم السلام', 'location' => 'القطاع الشمالي', 'capacity' => 400],
        ['name' => 'مخيم النور', 'location' => 'القطاع الشرقي', 'capacity' => 550],
        ['name' => 'مخيم الأمل', 'location' => 'القطاع الغربي', 'capacity' => 700],
        ['name' => 'مخيم الرحمة', 'location' => 'القطاع الجنوبي', 'capacity' => 850],
    ];

    /**
     * One capacity per unit, repeated in every camp. Mixed on purpose: a fixture
     * where every unit holds the same number never shows what a nearly-full camp
     * looks like next to a half-empty one.
     *
     * @var list<int>
     */
    private const SHELTER_CAPACITIES = [4, 6, 3, 8, 5, 4, 7, 3, 6, 5, 4, 8];

    /** @var list<string> */
    private const SHELTER_TYPES = ['tent', 'caravan', 'room'];

    /** @var list<string> */
    private const MALE_NAMES = [
        'أحمد', 'محمد', 'عمر', 'خالد', 'يوسف', 'إبراهيم', 'مصطفى', 'سامر', 'باسل', 'زياد',
        'حسن', 'كريم', 'طارق', 'وليد', 'ماهر', 'نبيل', 'رامي', 'فادي', 'عماد', 'جميل',
    ];

    /** @var list<string> */
    private const FEMALE_NAMES = [
        'فاطمة', 'مريم', 'عائشة', 'هدى', 'ليلى', 'نور', 'سلمى', 'رنا', 'دعاء', 'ياسمين',
        'أمل', 'سناء', 'هبة', 'ريم', 'لمى', 'بشرى', 'وفاء', 'سمر', 'آية', 'جنى',
    ];

    /** @var list<string> */
    private const FAMILY_NAMES = [
        'الحسن', 'الخطيب', 'العلي', 'الأحمد', 'السيد', 'الحاج', 'الشامي', 'الحلبي', 'الدرويش', 'النجار',
        'الحداد', 'العمر', 'الصالح', 'القاسم', 'الرفاعي', 'الزعبي', 'المصري', 'الطويل', 'الخوري', 'السعيد',
    ];

    /** @var list<string> */
    private const MALE_RELATIONS = ['ابن', 'زوج', 'والد', 'أخ'];

    /** @var list<string> */
    private const FEMALE_RELATIONS = ['ابنة', 'زوجة', 'والدة', 'أخت'];

    public function run(): void
    {
        $this->clearPreviousRun();

        $camps = $this->seedCamps();
        $shelters = $this->seedShelters($camps);
        $households = $this->seedHouseholds();

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
     * Remove what an earlier run of this seeder created.
     *
     * Needed because the run is deterministic: the same code produces the same
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
     * DatabaseSeeder already creates some of these by name, and the column is
     * unique — so an existing camp is reused rather than duplicated, which is
     * also what makes this seeder safe to re-run.
     *
     * @return Collection<int, Camp>
     */
    private function seedCamps(): Collection
    {
        return collect(self::CAMPS)->map(fn (array $camp): Camp => Camp::firstOrCreate(
            ['name' => $camp['name']],
            [
                'location' => $camp['location'],
                'capacity' => $camp['capacity'],
                'status' => 'active',
            ],
        ));
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
            ->map(fn (int $number): Shelter => Shelter::firstOrCreate(
                [
                    'camp_id' => $camp->id,
                    'code' => sprintf('%s%02d-%03d', chr(65 + $campIndex), $campIndex + 1, $number),
                ],
                [
                    'type' => self::SHELTER_TYPES[$number % count(self::SHELTER_TYPES)],
                    'capacity' => self::SHELTER_CAPACITIES[($number - 1) % count(self::SHELTER_CAPACITIES)],
                    'status' => 'active',
                ],
            )));
    }

    /**
     * @return Collection<int, Household>
     */
    private function seedHouseholds(): Collection
    {
        return collect(range(1, self::HOUSEHOLD_COUNT))->map(fn (int $number): Household => Household::firstOrCreate(
            ['household_code' => sprintf(self::HOUSEHOLD_PREFIX.'%04d', $number)],
            ['status' => 'active'],
        ));
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

        return collect(range(0, self::REFUGEE_COUNT - 1))
            ->map(fn (int $index): Refugee => Refugee::create(
                $this->refugeeAttributes($index, $slots, $camps, $households)
            ));
    }

    /**
     * Everything one refugee row holds, derived from its index alone.
     *
     * @param  list<Shelter>  $slots
     * @param  Collection<int, Camp>  $camps
     * @param  Collection<int, Household>  $households
     * @return array<string, mixed>
     */
    private function refugeeAttributes(int $index, array $slots, Collection $camps, Collection $households): array
    {
        // Alternating rather than drawn, so the population is exactly half and
        // half and the first name always matches the gender beside it.
        $isMale = $index % 2 === 0;
        $names = $isMale ? self::MALE_NAMES : self::FEMALE_NAMES;

        // Strides that share no factor with the list length walk the whole list
        // before repeating, so the three parts of a name vary independently
        // instead of moving together and producing twenty names for two hundred
        // people. Some full names still land twice, which is wanted: it is what
        // exercises the assistant's ambiguous-name answer.
        $firstName = $names[intdiv($index, 2) % count($names)];
        $fatherName = self::MALE_NAMES[($index * 3 + 5) % count(self::MALE_NAMES)];
        // "أحمد أحمد الحسن" reads as a data-entry slip rather than as a name.
        if ($fatherName === $firstName) {
            $fatherName = self::MALE_NAMES[($index * 3 + 6) % count(self::MALE_NAMES)];
        }
        $familyName = self::FAMILY_NAMES[($index * 7) % count(self::FAMILY_NAMES)];

        $age = 2 + ($index * 11) % 68;
        // Anchored to the start of the year rather than to today, so two runs a
        // week apart still write the same dates, while the ages themselves stay
        // sensible as the years pass.
        $birthDate = Carbon::now()->startOfYear()->subYears($age)->subDays(($index * 37) % 365);

        $housedRank = $this->quota($index, 137);
        $shelter = $housedRank < self::HOUSED_COUNT ? $slots[$housedRank] : null;

        $household = $this->quota($index, 91) < self::IN_FAMILY_COUNT
            ? $households[$this->quota($index, 53) % $households->count()]
            : null;

        return [
            'first_name' => $firstName,
            'father_name' => $fatherName,
            'last_name' => $familyName,
            'gender' => $isMale ? 'male' : 'female',
            'date_of_birth' => $birthDate->toDateString(),
            'nationality' => 'سوري',
            'document_number' => sprintf(self::DOCUMENT_PREFIX.'%06d', $index + 1),
            'phone' => sprintf('09%08d', 11000000 + $index * 7919),
            'marital_status' => $age < 20 ? 'single' : (($index % 3 === 0) ? 'single' : 'married'),
            'status' => 'active',
            // A camp is always required. A housed refugee takes the camp holding
            // their unit — the two disagreeing would put someone in a unit that
            // is not in their own camp.
            'current_camp_id' => $shelter?->camp_id ?? $camps[$index % $camps->count()]->id,
            'current_shelter_id' => $shelter?->id,
            // Kept in step with the unit on purpose: the archive guard reads this
            // column, not the foreign key, so the two disagreeing would make a
            // refugee impossible to archive for no visible reason.
            'housing_status' => $shelter !== null ? 'assigned' : 'unassigned',
            'presence_status' => $this->quota($index, 29) < self::OUTSIDE_COUNT ? 'outside' : 'inside',
            'household_id' => $household?->id,
            'relation_to_head' => $household === null
                ? null
                : ($isMale ? self::MALE_RELATIONS : self::FEMALE_RELATIONS)[$index % 4],
        ];
    }

    /**
     * Where this row falls in a shuffled-looking but fixed ordering of the
     * population, as a number from 0 to REFUGEE_COUNT - 1.
     *
     * Multiplying the index by a stride that shares no factor with the total
     * visits every position exactly once, so "rank below N" picks exactly N
     * rows, scattered through the sequence rather than falling in one block.
     * That is what replaces the coin flip: exact quotas, and the same fixture
     * on every machine without a random number generator.
     */
    private function quota(int $index, int $stride): int
    {
        return ($index * $this->coprimeWith($stride, self::REFUGEE_COUNT)) % self::REFUGEE_COUNT;
    }

    /**
     * The first number at or above $stride that shares no factor with $count.
     *
     * The strides above are already chosen to be coprime with 200; this keeps
     * the quotas exact if REFUGEE_COUNT is ever changed to a value that would
     * make one of them fall into a short cycle.
     */
    private function coprimeWith(int $stride, int $count): int
    {
        while ($this->greatestCommonDivisor($stride, $count) !== 1) {
            $stride++;
        }

        return $stride;
    }

    private function greatestCommonDivisor(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->greatestCommonDivisor($b, $a % $b);
    }

    /**
     * One entry per free space, ordered so the units fill evenly.
     *
     * Handing out spaces rather than units is what stops a unit of capacity 3
     * from being given six residents, which would show as occupancy above 100%
     * on the dashboard. Taking one space from every unit before returning for a
     * second is what spreads the population over all four camps instead of
     * filling the first two and leaving the rest empty.
     *
     * @param  Collection<int, Shelter>  $shelters
     * @return list<Shelter>
     */
    private function freeSpaces(Collection $shelters): array
    {
        $slots = [];

        for ($space = 0; $space < max(self::SHELTER_CAPACITIES); $space++) {
            foreach ($shelters as $shelter) {
                if ($space < (int) $shelter->capacity) {
                    $slots[] = $shelter;
                }
            }
        }

        return $slots;
    }

    /**
     * Every family that ended up with members gets its eldest as head.
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
                ->orderBy('date_of_birth')
                ->first();

            if ($head === null) {
                continue;
            }

            $household->update(['head_of_household_id' => $head->id]);
            $head->update(['relation_to_head' => 'رب الأسرة']);
        }
    }
}
