<?php

namespace Database\Factories;

use App\Models\Camp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Camp>
 */
class CampFactory extends Factory
{
    protected $model = Camp::class;

    /**
     * Values come from these arrays rather than from Faker, so nothing here
     * needs a package that an install done with `--no-dev` would leave out.
     * They are Arabic besides, which the previous Faker defaults were not —
     * an English city name in the location column of an Arabic camp system
     * made every screenshot look like test data, because it was.
     *
     * @var list<string>
     */
    private const NAMES = ['السلام', 'النور', 'الأمل', 'الرحمة', 'الوفاء', 'الكرامة', 'البشائر', 'اليرموك'];

    /** @var list<string> */
    private const LOCATIONS = ['القطاع الشمالي', 'القطاع الشرقي', 'القطاع الغربي', 'القطاع الجنوبي', 'القطاع الأوسط'];

    /**
     * Counts every camp this process has built, which is what replaces Faker's
     * `unique()`. The name always carries it, so a camp made here can never
     * collide with one CampStructureSeeder created under a plain name.
     */
    private static int $made = 0;

    public function definition(): array
    {
        $number = ++self::$made;

        return [
            'name' => 'مخيم '.self::NAMES[$number % count(self::NAMES)].' '.$number,
            'location' => self::LOCATIONS[$number % count(self::LOCATIONS)],
            // Spread over a realistic range without a random draw: the figure
            // only has to differ between camps, not be unpredictable.
            'capacity' => 200 + ($number % 20) * 150,
            'status' => 'active',
            'notes' => null,
        ];
    }
}
