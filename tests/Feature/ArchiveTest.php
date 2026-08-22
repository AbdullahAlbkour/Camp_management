<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_camp_can_be_archived_and_restored(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();

        $this->delete(route('archive.store', ['camps', $camp->id]))->assertRedirect(route('camps.index'));
        $this->assertSoftDeleted('camps', ['id' => $camp->id]);

        $this->post(route('archive.restore', ['camps', $camp->id]))->assertRedirect();
        $this->assertNotSoftDeleted('camps', ['id' => $camp->id]);
    }

    public function test_a_camp_holding_active_refugees_cannot_be_archived(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        Refugee::factory()->create(['current_camp_id' => $camp->id, 'status' => 'active']);

        $this->from(route('camps.index'))
            ->delete(route('archive.store', ['camps', $camp->id]))
            ->assertSessionHasErrors('archive');

        $this->assertNotSoftDeleted('camps', ['id' => $camp->id]);
    }

    public function test_an_occupied_shelter_cannot_be_archived(): void
    {
        $this->actingAsRole('housing_officer');
        $shelter = Shelter::factory()->capacity(3)->create();
        Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create();

        $this->from(route('shelters.index'))
            ->delete(route('archive.store', ['shelters', $shelter->id]))
            ->assertSessionHasErrors('archive');
    }

    public function test_a_household_with_active_members_cannot_be_archived(): void
    {
        $this->actingAsRole('registration_officer');
        $household = Household::factory()->create();
        Refugee::factory()->create(['household_id' => $household->id, 'status' => 'active']);

        $this->from(route('households.index'))
            ->delete(route('archive.store', ['households', $household->id]))
            ->assertSessionHasErrors('archive');
    }

    public function test_a_housed_refugee_cannot_be_archived(): void
    {
        $this->actingAsRole('registration_officer');
        $shelter = Shelter::factory()->capacity(3)->create();
        $refugee = Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create();

        $this->from(route('refugees.index'))
            ->delete(route('archive.store', ['refugees', $refugee->id]))
            ->assertSessionHasErrors('archive');
    }

    public function test_an_archived_record_disappears_from_normal_listings(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create(['name' => 'مخيم مؤرشف']);

        $this->delete(route('archive.store', ['camps', $camp->id]));

        $this->get(route('camps.index'))->assertOk()->assertDontSee('مخيم مؤرشف');
        $this->get(route('archive.index', ['resource' => 'camps']))->assertOk()->assertSee('مخيم مؤرشف');
    }

    public function test_archiving_and_restoring_are_audited(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();

        $this->delete(route('archive.store', ['camps', $camp->id]));
        $this->post(route('archive.restore', ['camps', $camp->id]));

        $this->assertTrue(AuditLog::where('action', 'archive')->where('module', 'camps')->exists());
        $this->assertTrue(AuditLog::where('action', 'restore')->where('module', 'camps')->exists());
    }

    public function test_a_role_without_rights_cannot_archive_that_resource(): void
    {
        $this->actingAsRole('aid_officer');
        $camp = Camp::factory()->create();

        $this->delete(route('archive.store', ['camps', $camp->id]))->assertForbidden();
        $this->assertNotSoftDeleted('camps', ['id' => $camp->id]);
    }

    public function test_the_archive_browser_only_lists_resources_the_user_may_manage(): void
    {
        $this->actingAsRole('aid_officer');

        $this->get(route('archive.index'))->assertOk()->assertSee('الجهات الداعمة');
        $this->get(route('archive.index', ['resource' => 'camps']))->assertForbidden();
    }
}
