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
