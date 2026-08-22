<?php

namespace Tests\Feature;

use App\Models\MedicalRecord;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_renders_with_data_on_any_database_driver(): void
    {
        $this->actingAsRole('manager');

        $shelter = Shelter::factory()->capacity(4)->create();
        Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create();
        MedicalRecord::factory()->create(['record_date' => now()->subMonth()->toDateString()]);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_the_live_endpoint_returns_stats_and_charts(): void
    {
        $this->actingAsRole('manager');
        Refugee::factory()->count(2)->create();

        $this->getJson(route('dashboard.live'))
            ->assertOk()
            ->assertJsonStructure(['stats', 'healthScore', 'criticalTasks', 'charts', 'notifications'])
            ->assertJsonPath('stats.refugees', 2);
    }

    public function test_the_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
