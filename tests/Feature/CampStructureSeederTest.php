<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use Database\Seeders\CampStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The fixture data: camps holding units, and a population scattered over them
 * with some people in a family or a unit and some without.
 */
class CampStructureSeederTest extends TestCase
{
    use RefreshDatabase;

    private function runSeeder(): void
    {
        $this->seed(CampStructureSeeder::class);
    }

    public function test_it_builds_the_structure_before_the_population(): void
    {
        $this->runSeeder();

        $this->assertSame(4, Camp::count());
        $this->assertSame(48, Shelter::count());
        $this->assertSame(40, Household::where('household_code', 'like', 'FAM-%')->count());
        $this->assertSame(200, Refugee::where('document_number', 'like', 'POP-%')->count());

        // Every unit belongs to a camp: the hierarchy is the point of the fixture.
        $this->assertSame(0, Shelter::whereNull('camp_id')->count());
    }

    public function test_the_population_is_split_between_housed_and_unhoused(): void
    {
        $this->runSeeder();

        $population = Refugee::where('document_number', 'like', 'POP-%');

        $housed = (clone $population)->whereNotNull('current_shelter_id')->count();
        $unhoused = (clone $population)->whereNull('current_shelter_id')->count();

        // Both sides have to be represented, otherwise the fixture exercises
        // only half of the screens that read this column.
        $this->assertGreaterThan(0, $housed);
        $this->assertGreaterThan(0, $unhoused);
        $this->assertSame(200, $housed + $unhoused);
    }

    public function test_the_population_is_split_between_families_and_singles(): void
    {
        $this->runSeeder();

        $population = Refugee::where('document_number', 'like', 'POP-%');

        $inFamily = (clone $population)->whereNotNull('household_id')->count();
        $alone = (clone $population)->whereNull('household_id')->count();

        $this->assertGreaterThan(0, $inFamily);
        $this->assertGreaterThan(0, $alone);
        $this->assertSame(200, $inFamily + $alone);
    }

    public function test_no_unit_is_filled_past_its_capacity(): void
    {
        $this->runSeeder();

        $overfilled = Shelter::withCount(['refugees' => fn ($query) => $query->where('status', 'active')])
            ->get()
            ->filter(fn (Shelter $shelter) => $shelter->refugees_count > $shelter->capacity);

        $this->assertCount(0, $overfilled, 'A unit was given more residents than it has spaces.');
    }

    public function test_housing_status_always_agrees_with_the_unit(): void
    {
        $this->runSeeder();

        // The archive guard reads this column rather than the foreign key, so
        // the two disagreeing makes a record impossible to archive for no
        // visible reason.
        $this->assertSame(0, Refugee::whereNotNull('current_shelter_id')->where('housing_status', '!=', 'assigned')->count());
        $this->assertSame(0, Refugee::whereNull('current_shelter_id')->where('housing_status', 'assigned')->count());
    }

    public function test_a_housed_refugee_is_in_the_camp_that_holds_their_unit(): void
    {
        $this->runSeeder();

        $mismatched = Refugee::whereNotNull('current_shelter_id')
            ->whereRaw('current_camp_id != (select camp_id from shelters where shelters.id = refugees.current_shelter_id)')
            ->count();

        $this->assertSame(0, $mismatched);
    }

    public function test_arabic_search_reaches_the_seeded_records(): void
    {
        $this->runSeeder();

        // Written through Eloquent rather than bulk-inserted, so the model event
        // that folds the name into search_text actually fires.
        $this->assertSame(0, Refugee::whereNull('search_text')->orWhere('search_text', '')->count());
    }

    public function test_every_family_that_has_members_has_a_head(): void
    {
        $this->runSeeder();

        $headless = Household::where('household_code', 'like', 'FAM-%')
            ->whereNull('head_of_household_id')
            ->has('members')
            ->count();

        $this->assertSame(0, $headless);
    }

    public function test_no_family_holds_two_people_with_the_same_name(): void
    {
        $this->runSeeder();

        // Households are handed out on a fixed stride, so members of one family
        // sit an exact multiple of the name list apart. Picking the first name
        // by "index modulo twenty" therefore gave all three members of a family
        // the same name — three sisters called هدى مصطفى النجار.
        $collisions = Household::where('household_code', 'like', 'FAM-%')
            ->with('members')
            ->get()
            ->filter(fn (Household $household) => $household->members->count()
                !== $household->members->unique(fn (Refugee $member) => $member->full_name)->count());

        $this->assertCount(0, $collisions, 'A family was given two members with the same full name.');
    }

    public function test_it_runs_without_the_faker_package(): void
    {
        // Faker lives in require-dev, so an install done with --no-dev — the
        // normal way to set this application up on the machine that will use it
        // — does not have it. A factory call anywhere in this path would fail
        // there as "Call to a member function on null", which names neither the
        // cause nor the cure, so the dependency is asserted away rather than
        // left to be discovered on someone else's XAMPP.
        foreach ([
            'database/seeders/CampStructureSeeder.php',
            'database/factories/CampFactory.php',
        ] as $file) {
            $code = $this->sourceWithoutComments(base_path($file));

            $this->assertStringNotContainsStringIgnoringCase('faker', $code, $file.' still reaches for Faker.');
            $this->assertStringNotContainsString('fake(', $code, $file.' still reaches for Faker.');
            $this->assertStringNotContainsString('::factory(', $code, $file.' still builds rows through a factory, which needs Faker.');
        }
    }

    public function test_the_population_is_written_in_arabic(): void
    {
        $this->runSeeder();

        // Matched in PHP rather than in SQL: SQLite ships without REGEXP, and
        // the suite runs on it while the application runs on MySQL.
        $latin = Refugee::where('document_number', 'like', 'POP-%')
            ->pluck('first_name')
            ->merge(Refugee::where('document_number', 'like', 'POP-%')->pluck('last_name'))
            ->filter(fn (string $name) => preg_match('/[A-Za-z]/', $name) === 1)
            ->count();

        // Faker's defaults wrote English names into an Arabic register, which
        // made every screenshot of the fixture look like what it was.
        $this->assertSame(0, $latin);
    }

    public function test_two_runs_produce_the_same_fixture(): void
    {
        // Nothing here draws a random number, so the same code has to describe
        // the same camp on every machine — which is what lets a figure quoted
        // from a screenshot still match the database a month later.
        $this->runSeeder();
        $first = $this->fingerprint();

        $this->runSeeder();

        $this->assertSame($first, $this->fingerprint());
    }

    /**
     * The seeded population reduced to the columns that decide what the screens
     * show, in a stable order.
     */
    private function fingerprint(): string
    {
        return Refugee::where('document_number', 'like', 'POP-%')
            ->orderBy('document_number')
            ->get(['document_number', 'first_name', 'father_name', 'last_name', 'gender', 'date_of_birth', 'housing_status', 'presence_status'])
            ->map(fn (Refugee $refugee) => implode('|', [
                $refugee->document_number,
                $refugee->full_name,
                $refugee->gender,
                $refugee->date_of_birth?->toDateString(),
                $refugee->housing_status,
                $refugee->presence_status,
            ]))
            ->implode("\n");
    }

    /**
     * Source with comments stripped, so a mention of Faker in a docblock does
     * not fail the assertion that the code does not use it.
     */
    private function sourceWithoutComments(string $path): string
    {
        return collect(token_get_all((string) file_get_contents($path)))
            ->reject(fn ($token) => is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true))
            ->map(fn ($token) => is_array($token) ? $token[1] : $token)
            ->implode('');
    }

    public function test_running_it_twice_does_not_duplicate_or_collide(): void
    {
        // The run is deterministic, so a second pass regenerates the same unique
        // document numbers — it has to clear its own rows first, and both models
        // soft-delete, which a plain delete would not survive.
        $this->runSeeder();
        $this->runSeeder();

        $this->assertSame(200, Refugee::where('document_number', 'like', 'POP-%')->count());
        $this->assertSame(4, Camp::count());
        $this->assertSame(48, Shelter::count());
    }
}
