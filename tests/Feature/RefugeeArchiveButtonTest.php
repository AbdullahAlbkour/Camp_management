<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting a refugee from the profile screen.
 */
class RefugeeArchiveButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_offers_the_delete_button_to_a_registration_officer(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create(['current_camp_id' => Camp::factory()]);

        $this->get(route('refugees.show', $refugee))
            ->assertOk()
            // The DELETE route shares its URI with the profile it sits on, so
            // the confirm text is what actually identifies the button.
            ->assertSee('سيُنقل سجل', false)
            // It has to say the record is recoverable: that is what the person
            // is deciding on.
            ->assertSee('الأرشيف', false)
            ->assertSee('value="DELETE"', false);
    }

    public function test_a_name_containing_a_quote_does_not_break_the_confirm(): void
    {
        // {{ }} escapes for HTML, and the attribute is decoded again before the
        // JS parses it — so a raw apostrophe would close the string literal and
        // leave the button silently dead.
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create([
            'current_camp_id' => Camp::factory(),
            'first_name' => "عبد'الله",
        ]);

        $this->get(route('refugees.show', $refugee))
            ->assertOk()
            ->assertSee('سيُنقل سجل', false)
            // The quote leaves as a unicode escape rather than bare, which is
            // what keeps it from ending the JS string early.
            ->assertSee('\\u0027', false)
            ->assertDontSee("عبد'الله", false);
    }

    public function test_a_role_without_the_permission_is_not_offered_the_button(): void
    {
        $this->actingAsRole('medical_officer');
        $refugee = Refugee::factory()->create(['current_camp_id' => Camp::factory()]);

        $this->get(route('refugees.show', $refugee))
            ->assertOk()
            ->assertDontSee('سيُنقل سجل', false);
    }

    public function test_deleting_archives_the_record_rather_than_erasing_it(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create(['current_camp_id' => Camp::factory()]);

        $this->delete(route('refugees.destroy', $refugee))
            ->assertRedirect(route('refugees.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('refugees', ['id' => $refugee->id]);
        $this->assertNotNull(Refugee::withTrashed()->find($refugee->id));
    }

    public function test_the_delete_is_written_to_the_audit_trail(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create(['current_camp_id' => Camp::factory()]);

        $this->delete(route('refugees.destroy', $refugee));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'archive',
            'module' => 'refugees',
            'auditable_id' => $refugee->id,
        ]);
    }

    public function test_someone_still_living_in_a_unit_cannot_be_deleted(): void
    {
        // The guard lives in ArchiveService; routing the button through it is
        // the reason this holds without the controller repeating the rule.
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();
        $shelter = Shelter::factory()->capacity(3)->create(['camp_id' => $camp->id]);
        $refugee = Refugee::factory()->inShelter($shelter->id, $camp->id)->create();

        $this->from(route('refugees.show', $refugee))
            ->delete(route('refugees.destroy', $refugee))
            ->assertRedirect(route('refugees.show', $refugee))
            ->assertSessionHasErrors('archive');

        $this->assertNotSoftDeleted('refugees', ['id' => $refugee->id]);
    }

    public function test_a_role_outside_the_permission_is_refused_the_route(): void
    {
        $this->actingAsRole('medical_officer');
        $refugee = Refugee::factory()->create(['current_camp_id' => Camp::factory()]);

        $this->delete(route('refugees.destroy', $refugee))->assertForbidden();

        $this->assertNotSoftDeleted('refugees', ['id' => $refugee->id]);
    }

    public function test_a_guest_cannot_reach_the_route(): void
    {
        $refugee = Refugee::factory()->create(['current_camp_id' => Camp::factory()]);

        $this->delete(route('refugees.destroy', $refugee))->assertRedirect(route('login'));
    }
}
