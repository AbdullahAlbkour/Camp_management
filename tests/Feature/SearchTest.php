<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_refugee_is_found_by_name(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create([
            'first_name' => 'سميرة',
            'father_name' => null,
            'last_name' => 'الأحمد',
        ]);

        $this->get(route('search.index', ['q' => 'سميرة']))
            ->assertOk()
            ->assertSee('سميرة الأحمد');
    }

    public function test_a_refugee_is_found_by_document_number(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'خالد', 'document_number' => 'DOC-99887']);

        $this->get(route('search.index', ['q' => 'DOC-99887']))
            ->assertOk()
            ->assertSee('خالد');
    }

    public function test_a_household_is_found_by_code(): void
    {
        $this->actingAsRole('registration_officer');
        Household::factory()->create(['household_code' => 'HH-4242']);

        $this->get(route('search.index', ['q' => 'HH-4242']))
            ->assertOk()
            ->assertSee('HH-4242');
    }

    public function test_shelters_are_hidden_from_roles_without_housing_access(): void
    {
        $this->actingAsRole('registration_officer');
        Shelter::factory()->create(['code' => 'ZZZ-1']);

        // Asserted against the JSON payload: the HTML page echoes the search term
        // back into the input, so "not on the page" is not the same as "not a result".
        $this->getJson(route('search.suggest', ['q' => 'ZZZ-1']))
            ->assertOk()
            ->assertJsonCount(0, 'groups');
    }

    public function test_a_housing_officer_can_find_shelters_and_camps(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create(['name' => 'مخيم الشمال']);
        Shelter::factory()->create(['code' => 'ZZZ-1', 'camp_id' => $camp->id]);

        $this->getJson(route('search.suggest', ['q' => 'ZZZ-1']))
            ->assertOk()
            ->assertJsonPath('groups.0.label', 'الوحدات السكنية');

        $this->getJson(route('search.suggest', ['q' => 'الشمال']))
            ->assertOk()
            ->assertJsonPath('groups.0.items.0.title', 'مخيم الشمال');
    }

    public function test_the_suggest_endpoint_returns_grouped_json(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create([
            'first_name' => 'سميرة',
            'father_name' => null,
            'last_name' => 'الأحمد',
        ]);

        $this->getJson(route('search.suggest', ['q' => 'سميرة']))
            ->assertOk()
            ->assertJsonPath('term', 'سميرة')
            ->assertJsonPath('groups.0.label', 'اللاجئون')
            ->assertJsonPath('groups.0.items.0.title', 'سميرة الأحمد');
    }

    public function test_an_empty_term_returns_no_groups(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create();

        $this->getJson(route('search.suggest', ['q' => '  ']))
            ->assertOk()
            ->assertJsonCount(0, 'groups');
    }

    public function test_search_requires_authentication(): void
    {
        $this->get(route('search.index', ['q' => 'anything']))->assertRedirect(route('login'));
    }
}
