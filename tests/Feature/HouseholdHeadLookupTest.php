<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Choosing the head of a household by typing rather than by scrolling a list.
 */
class HouseholdHeadLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_create_form_offers_a_search_box_not_a_dropdown(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->count(3)->create(['current_camp_id' => Camp::factory()]);

        $this->get(route('households.create'))
            ->assertOk()
            ->assertSee('data-async-select', false)
            ->assertSee(route('lookups.refugees'), false)
            ->assertSee('ابحث بالاسم أو رقم الوثيقة أو الهاتف', false);
    }

    public function test_the_form_no_longer_renders_every_refugee_as_an_option(): void
    {
        // The old field pulled the whole register into one <select>, which is
        // thousands of options on a real deployment.
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();
        $refugee = Refugee::factory()->create([
            'current_camp_id' => $camp->id,
            'first_name' => 'ميساء', 'father_name' => 'وليد', 'last_name' => 'الجابري',
        ]);

        $this->get(route('households.create'))
            ->assertOk()
            ->assertDontSee('<option value="'.$refugee->id.'"', false);
    }

    public function test_the_edit_form_shows_the_current_head_in_the_search_box(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();
        $head = Refugee::factory()->create([
            'current_camp_id' => $camp->id,
            'first_name' => 'ماهر', 'father_name' => 'سليم', 'last_name' => 'الدروبي',
        ]);
        $household = Household::factory()->create(['head_of_household_id' => $head->id]);

        $this->get(route('households.edit', $household))
            ->assertOk()
            ->assertSee('ماهر سليم الدروبي', false);
    }

    public function test_the_typed_head_is_still_saved_as_a_real_record(): void
    {
        // The column is a foreign key, so what the box resolves to has to be an
        // id — the typing is how it is found, not what is stored.
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();
        $head = Refugee::factory()->create(['current_camp_id' => $camp->id]);

        $this->post(route('households.store'), [
            'household_code' => 'HH-9001',
            'head_of_household_id' => $head->id,
            'status' => 'active',
        ])->assertRedirect();

        $household = Household::where('household_code', 'HH-9001')->firstOrFail();

        $this->assertSame($head->id, $household->head_of_household_id);
        // Naming a head also pulls them into the family, as it did before.
        $this->assertSame($household->id, $head->fresh()->household_id);
        $this->assertSame('رب الأسرة', $head->fresh()->relation_to_head);
    }

    public function test_a_head_that_is_not_a_real_refugee_is_rejected(): void
    {
        $this->actingAsRole('registration_officer');

        $this->post(route('households.store'), [
            'household_code' => 'HH-9002',
            'head_of_household_id' => 999999,
            'status' => 'active',
        ])->assertSessionHasErrors('head_of_household_id');

        $this->assertDatabaseMissing('households', ['household_code' => 'HH-9002']);
    }

    public function test_a_household_can_still_be_created_without_a_head(): void
    {
        $this->actingAsRole('registration_officer');

        $this->post(route('households.store'), [
            'household_code' => 'HH-9003',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('households', [
            'household_code' => 'HH-9003',
            'head_of_household_id' => null,
        ]);
    }

    public function test_the_lookup_endpoint_finds_a_refugee_by_typed_name(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();
        Refugee::factory()->create([
            'current_camp_id' => $camp->id,
            'first_name' => 'نادر', 'father_name' => 'حسام', 'last_name' => 'العطار',
        ]);

        // Folded on both sides, so the spelling without the hamza still lands.
        $response = $this->getJson(route('lookups.refugees', ['q' => 'نادر']));

        $response->assertOk();

        // Read as decoded JSON, not raw body: the response escapes Arabic to
        // \uXXXX, so the literal name never appears in the bytes.
        $names = array_column($response->json(), 'text');
        $this->assertContains('نادر حسام العطار', $names);
    }
}
