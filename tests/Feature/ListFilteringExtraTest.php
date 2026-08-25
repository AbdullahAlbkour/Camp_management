<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListFilteringExtraTest extends TestCase
{
    use RefreshDatabase;

    // ---- Households ----

    public function test_a_household_is_found_by_its_head_name_ignoring_hamza(): void
    {
        $this->actingAsRole('registration_officer');
        $head = Refugee::factory()->create(['first_name' => 'أنس', 'father_name' => null, 'last_name' => 'مرعي']);
        Household::factory()->create(['household_code' => 'HH-7001', 'head_of_household_id' => $head->id]);
        Household::factory()->create(['household_code' => 'HH-8002']);

        $this->get(route('households.index', ['q' => 'انس']))
            ->assertOk()
            ->assertSee('HH-7001')
            ->assertDontSee('HH-8002');
    }

    public function test_households_filter_by_camp_through_their_members(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();

        $inCamp = Household::factory()->create(['household_code' => 'HH-INCAMP']);
        Refugee::factory()->create(['household_id' => $inCamp->id, 'current_camp_id' => $camp->id]);

        $elsewhere = Household::factory()->create(['household_code' => 'HH-OTHER']);
        Refugee::factory()->create(['household_id' => $elsewhere->id]);

        $this->get(route('households.index', ['camp_id' => $camp->id]))
            ->assertOk()
            ->assertSee('HH-INCAMP')
            ->assertDontSee('HH-OTHER');
    }

    public function test_households_filter_by_member_count_range(): void
    {
        $this->actingAsRole('registration_officer');
        $small = Household::factory()->create(['household_code' => 'HH-SMALL']);
        Refugee::factory()->create(['household_id' => $small->id]);

        $large = Household::factory()->create(['household_code' => 'HH-LARGE']);
        Refugee::factory()->count(5)->create(['household_id' => $large->id]);

        $this->get(route('households.index', ['members_min' => 3]))
            ->assertOk()
            ->assertSee('HH-LARGE')
            ->assertDontSee('HH-SMALL');
    }

    public function test_households_filter_by_partial_housing(): void
    {
        $this->actingAsRole('registration_officer');
        $shelter = Shelter::factory()->capacity(5)->create();

        $partial = Household::factory()->create(['household_code' => 'HH-PARTIAL']);
        Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create(['household_id' => $partial->id]);
        Refugee::factory()->create(['household_id' => $partial->id, 'current_camp_id' => $shelter->camp_id]);

        $fullyHoused = Household::factory()->create(['household_code' => 'HH-HOUSED']);
        Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create(['household_id' => $fullyHoused->id]);

        $this->get(route('households.index', ['housing' => 'partial']))
            ->assertOk()
            ->assertSee('HH-PARTIAL')
            ->assertDontSee('HH-HOUSED');
    }

    public function test_households_without_a_head_can_be_isolated(): void
    {
        $this->actingAsRole('registration_officer');
        $head = Refugee::factory()->create();
        Household::factory()->create(['household_code' => 'HH-WITHHEAD', 'head_of_household_id' => $head->id]);
        Household::factory()->create(['household_code' => 'HH-NOHEAD', 'head_of_household_id' => null]);

        $this->get(route('households.index', ['no_head' => '1']))
            ->assertOk()
            ->assertSee('HH-NOHEAD')
            ->assertDontSee('HH-WITHHEAD');
    }

    // ---- Shelters ----

    public function test_the_shelter_screen_now_exposes_its_filters(): void
    {
        // The camp filter existed in the controller but crud.index rendered no
        // form, so there was no way to reach it from the screen.
        $this->actingAsRole('housing_officer');
        Shelter::factory()->create();

        $this->get(route('shelters.index'))
            ->assertOk()
            ->assertSee('فلترة متقدمة')
            ->assertSee('name="camp_id"', false);
    }

    public function test_shelters_filter_by_camp_and_type(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        Shelter::factory()->create(['code' => 'TNT-1', 'camp_id' => $camp->id, 'type' => 'tent']);
        Shelter::factory()->create(['code' => 'ROOM-1', 'camp_id' => $camp->id, 'type' => 'room']);
        Shelter::factory()->create(['code' => 'FAR-1', 'type' => 'tent']);

        $this->get(route('shelters.index', ['camp_id' => $camp->id, 'type' => 'tent']))
            ->assertOk()
            ->assertSee('TNT-1')
            ->assertDontSee('ROOM-1')
            ->assertDontSee('FAR-1');
    }

    public function test_shelters_filter_by_occupancy(): void
    {
        $this->actingAsRole('housing_officer');

        $full = Shelter::factory()->capacity(1)->create(['code' => 'FULL-1']);
        Refugee::factory()->inShelter($full->id, $full->camp_id)->create();

        $available = Shelter::factory()->capacity(4)->create(['code' => 'FREE-1']);
        Refugee::factory()->inShelter($available->id, $available->camp_id)->create();

        Shelter::factory()->capacity(3)->create(['code' => 'EMPTY-1']);

        $this->get(route('shelters.index', ['occupancy' => 'full']))
            ->assertOk()->assertSee('FULL-1')->assertDontSee('FREE-1');

        $this->get(route('shelters.index', ['occupancy' => 'available']))
            ->assertOk()->assertSee('FREE-1')->assertDontSee('FULL-1');

        $this->get(route('shelters.index', ['occupancy' => 'empty']))
            ->assertOk()->assertSee('EMPTY-1')->assertDontSee('FREE-1');
    }

    public function test_shelters_search_by_code(): void
    {
        $this->actingAsRole('housing_officer');
        Shelter::factory()->create(['code' => 'ALPHA-9']);
        Shelter::factory()->create(['code' => 'BETA-3']);

        $this->get(route('shelters.index', ['q' => 'alpha']))
            ->assertOk()
            ->assertSee('ALPHA-9')
            ->assertDontSee('BETA-3');
    }

    // ---- Sorting, paging and chips ----

    public function test_results_can_be_sorted_by_a_whitelisted_column(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'ياسر', 'father_name' => null, 'last_name' => 'أول']);
        Refugee::factory()->create(['first_name' => 'ابتسام', 'father_name' => null, 'last_name' => 'ثان']);

        $response = $this->get(route('refugees.index', ['sort' => 'name', 'dir' => 'asc']))->assertOk();

        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, 'ياسر أول'),
            strpos($body, 'ابتسام ثان'),
            'Ascending sort by name should place ابتسام before ياسر.'
        );
    }

    public function test_an_unknown_sort_key_is_ignored_rather_than_injected(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->count(3)->create();

        $this->get(route('refugees.index', ['sort' => 'first_name); DROP TABLE refugees;--', 'dir' => 'asc']))
            ->assertOk();

        $this->assertSame(3, Refugee::count(), 'The table must still be there.');
    }

    public function test_page_size_is_bounded(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->count(30)->create();

        // An out-of-range page size falls back to the default rather than
        // letting a crafted query string pull the whole table.
        $response = $this->get(route('refugees.index', ['per_page' => 100000]))->assertOk();

        // Falling back to 20 per page means 30 records span two pages.
        $this->assertSame(20, $response->viewData('rows')->count());
        $this->assertSame(2, $response->viewData('rows')->lastPage());
    }

    public function test_applied_filters_are_shown_as_removable_chips(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create(['name' => 'مخيم الشمال']);
        Refugee::factory()->create(['current_camp_id' => $camp->id]);

        $this->get(route('refugees.index', ['camp_id' => $camp->id, 'gender' => 'male']))
            ->assertOk()
            ->assertSee('الفلاتر المطبقة')
            ->assertSee('مخيم الشمال')
            ->assertSee('ذكر');
    }

    public function test_search_and_filters_survive_pagination(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();
        Refugee::factory()->count(25)->create(['current_camp_id' => $camp->id, 'gender' => 'female']);

        $this->get(route('refugees.index', ['camp_id' => $camp->id, 'gender' => 'female']))
            ->assertOk()
            ->assertSee('camp_id='.$camp->id, false)
            ->assertSee('gender=female', false);
    }
}
