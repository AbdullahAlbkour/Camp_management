<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Refugee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterBarRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_refugee_filter_bar_renders_every_declared_control(): void
    {
        $this->actingAsRole('registration_officer');
        Camp::factory()->create(['name' => 'مخيم السلام']);

        $response = $this->get(route('refugees.index'))->assertOk();

        foreach (['q', 'camp_id', 'housing_status', 'presence_status', 'gender', 'status', 'age_min', 'age_max', 'unhoused_days', 'per_page'] as $control) {
            $response->assertSee('name="'.$control.'"', false);
        }

        $response->assertSee('مخيم السلام');
    }

    public function test_advanced_filters_stay_collapsed_until_one_is_applied(): void
    {
        $this->actingAsRole('registration_officer');

        // Asserted against the rendered attribute rather than a substring of the
        // whole page, so Blade's whitespace around the conditional cannot make
        // the test pass or fail for the wrong reason.
        $this->assertTrue($this->advancedPanelIsHidden(
            $this->get(route('refugees.index'))->assertOk()->getContent()
        ), 'With no filters applied the advanced panel should start collapsed.');

        $this->assertFalse($this->advancedPanelIsHidden(
            $this->get(route('refugees.index', ['gender' => 'female']))->assertOk()->getContent()
        ), 'An applied filter must not be hidden inside a collapsed panel.');
    }

    private function advancedPanelIsHidden(string $html): bool
    {
        $this->assertMatchesRegularExpression('/data-filter-advanced[^>]*>/', $html);
        preg_match('/data-filter-advanced([^>]*)>/', $html, $matches);

        return str_contains($matches[1] ?? '', 'hidden');
    }

    public function test_sortable_headers_carry_the_current_filters(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();
        Refugee::factory()->create(['current_camp_id' => $camp->id]);

        $this->get(route('refugees.index', ['camp_id' => $camp->id]))
            ->assertOk()
            // A sort link must not silently drop the filter already applied.
            ->assertSee('sort=name', false)
            ->assertSee('camp_id='.$camp->id, false);
    }

    public function test_every_list_screen_renders_its_filter_bar(): void
    {
        foreach ([
            'registration_officer' => ['refugees.index', 'households.index'],
            'housing_officer' => ['shelters.index'],
        ] as $role => $routes) {
            foreach ($routes as $route) {
                $this->actingAsRole($role);
                $this->get(route($route))
                    ->assertOk()
                    ->assertSee('data-filter-bar', false)
                    ->assertSee('فلترة متقدمة');
            }
        }
    }
}
