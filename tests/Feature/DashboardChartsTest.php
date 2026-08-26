<?php

namespace Tests\Feature;

use App\Models\AidDistribution;
use App\Models\AidType;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    // ---- Shelter occupancy breakdown ----

    public function test_shelter_states_split_units_into_three_exclusive_buckets(): void
    {
        $this->actingAsRole('manager');

        $full = Shelter::factory()->capacity(1)->create();
        Refugee::factory()->inShelter($full->id, $full->camp_id)->create();

        $partial = Shelter::factory()->capacity(4)->create();
        Refugee::factory()->inShelter($partial->id, $partial->camp_id)->create();

        Shelter::factory()->capacity(3)->create(); // empty

        $states = $this->get(route('dashboard'))->assertOk()->viewData('shelterStates');

        $this->assertSame(['ممتلئة', 'مشغولة جزئيًا', 'فارغة'], $states['labels']);
        $this->assertSame([1, 1, 1], $states['values']);
    }

    public function test_the_shelter_slices_total_the_number_of_active_units(): void
    {
        $this->actingAsRole('manager');

        Shelter::factory()->count(5)->capacity(2)->create();
        Shelter::factory()->capacity(2)->create(['status' => 'maintenance']);

        $states = $this->get(route('dashboard'))->assertOk()->viewData('shelterStates');

        // A donut is only honest if its slices add up to a real whole.
        $this->assertSame(5, array_sum($states['values']));
    }

    // ---- Refugee state breakdown ----

    public function test_refugee_states_split_the_active_population(): void
    {
        $this->actingAsRole('manager');

        $shelter = Shelter::factory()->capacity(9)->create();

        Refugee::factory()->count(3)->inShelter($shelter->id, $shelter->camp_id)
            ->create(['presence_status' => 'inside']);
        Refugee::factory()->count(2)->inShelter($shelter->id, $shelter->camp_id)
            ->create(['presence_status' => 'outside']);
        Refugee::factory()->count(4)->create(['housing_status' => 'unassigned']);
        Refugee::factory()->create(['status' => 'archived', 'housing_status' => 'unassigned']);

        $states = $this->get(route('dashboard'))->assertOk()->viewData('refugeeStates');

        $this->assertSame([3, 2, 4], $states['values']);
        $this->assertSame(9, array_sum($states['values']), 'Archived refugees are excluded.');
    }

    // ---- Aid this month ----

    public function test_aid_this_month_counts_only_the_current_month(): void
    {
        $this->actingAsRole('aid_officer');
        $type = AidType::factory()->create(['name' => 'سلة غذائية']);

        AidDistribution::factory()->count(3)->create([
            'aid_type_id' => $type->id,
            'distribution_date' => now()->startOfMonth()->toDateString(),
            'quantity' => 5,
        ]);
        AidDistribution::factory()->create([
            'aid_type_id' => $type->id,
            'distribution_date' => now()->subMonths(2)->toDateString(),
        ]);

        $aid = $this->get(route('dashboard'))->assertOk()->viewData('aidMonth');

        $this->assertSame(3, $aid['operations']);
        $this->assertSame(15.0, $aid['quantity']);
        $this->assertSame('سلة غذائية', $aid['top_type']);
    }

    public function test_beneficiaries_are_counted_distinctly_across_refugees_and_households(): void
    {
        $this->actingAsRole('aid_officer');
        $refugee = Refugee::factory()->create();
        $household = Household::factory()->create();

        // The same refugee twice must count once.
        AidDistribution::factory()->count(2)->create([
            'refugee_id' => $refugee->id,
            'household_id' => null,
            'distribution_date' => now()->toDateString(),
        ]);
        AidDistribution::factory()->create([
            'refugee_id' => null,
            'household_id' => $household->id,
            'distribution_date' => now()->toDateString(),
        ]);

        $aid = $this->get(route('dashboard'))->assertOk()->viewData('aidMonth');

        $this->assertSame(3, $aid['operations']);
        $this->assertSame(2, $aid['beneficiaries']);
    }

    public function test_no_comparison_is_claimed_when_last_month_had_none(): void
    {
        $this->actingAsRole('aid_officer');
        AidDistribution::factory()->create(['distribution_date' => now()->toDateString()]);

        $aid = $this->get(route('dashboard'))->assertOk()->viewData('aidMonth');

        $this->assertNull($aid['change_percentage'], 'Dividing by a zero baseline must not invent a percentage.');
    }

    public function test_an_empty_system_reports_zeroes_rather_than_failing(): void
    {
        $this->actingAsRole('manager');

        $response = $this->get(route('dashboard'))->assertOk();

        $this->assertSame([0, 0, 0], $response->viewData('shelterStates')['values']);
        $this->assertSame([0, 0, 0], $response->viewData('refugeeStates')['values']);
        $this->assertSame(0, $response->viewData('aidMonth')['operations']);
        $this->assertSame('—', $response->viewData('aidMonth')['top_type']);
    }

    // ---- Wiring ----

    public function test_the_live_endpoint_carries_the_new_series(): void
    {
        $this->actingAsRole('manager');
        Shelter::factory()->capacity(2)->create();

        $this->getJson(route('dashboard.live'))
            ->assertOk()
            ->assertJsonStructure([
                'charts' => ['shelterStates' => ['labels', 'values'], 'refugeeStates', 'aidMonth'],
                'aidMonth' => ['operations', 'beneficiaries', 'quantity', 'top_type'],
            ]);
    }

    public function test_the_new_chart_tabs_render_for_the_right_roles(): void
    {
        $this->actingAsRole('housing_officer');
        $this->get(route('dashboard'))->assertOk()->assertSee('حالة الوحدات');

        $this->actingAsRole('aid_officer');
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('مساعدات الشهر')
            ->assertSee('مساعدات هذا الشهر');
    }

    public function test_the_aid_panel_is_hidden_from_roles_without_aid_access(): void
    {
        $this->actingAsRole('medical_officer');

        $this->get(route('dashboard'))->assertOk()->assertDontSee('مساعدات هذا الشهر');
    }
}
