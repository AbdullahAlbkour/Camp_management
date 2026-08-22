<?php

namespace Tests\Feature;

use App\Models\AidType;
use App\Models\Camp;
use App\Models\Checkpoint;
use App\Models\MedicalService;
use App\Models\Refugee;
use App\Models\Role;
use App\Models\Shelter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards on values the database or the domain cannot accept, but which the forms
 * previously let through.
 */
class ValidationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_refugee_cannot_be_born_in_the_future(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();

        $this->from(route('refugees.create'))
            ->post(route('refugees.store'), [
                'first_name' => 'خالد',
                'last_name' => 'الحسن',
                'gender' => 'male',
                'date_of_birth' => now()->addYear()->toDateString(),
                'current_camp_id' => $camp->id,
            ])
            ->assertSessionHasErrors('date_of_birth');
    }

    public function test_a_duplicate_shelter_code_in_the_same_camp_is_a_validation_error(): void
    {
        $this->actingAsRole('housing_officer');
        $shelter = Shelter::factory()->create(['code' => 'A-1']);

        // Previously no unique rule existed, so this hit the database constraint
        // and surfaced as a raw SQL error page instead of a form message.
        $this->from(route('shelters.create'))
            ->post(route('shelters.store'), [
                'camp_id' => $shelter->camp_id,
                'code' => 'A-1',
                'type' => 'tent',
                'capacity' => 4,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_the_same_shelter_code_is_allowed_in_a_different_camp(): void
    {
        $this->actingAsRole('housing_officer');
        Shelter::factory()->create(['code' => 'A-1']);
        $otherCamp = Camp::factory()->create();

        $this->post(route('shelters.store'), [
            'camp_id' => $otherCamp->id,
            'code' => 'A-1',
            'type' => 'tent',
            'capacity' => 4,
            'status' => 'active',
        ])->assertSessionHasNoErrors();
    }

    public function test_shelter_capacity_cannot_drop_below_current_occupancy(): void
    {
        $this->actingAsRole('housing_officer');
        $shelter = Shelter::factory()->capacity(4)->create();
        Refugee::factory()->count(3)->create([
            'current_camp_id' => $shelter->camp_id,
            'current_shelter_id' => $shelter->id,
            'housing_status' => 'assigned',
        ]);

        $this->from(route('shelters.edit', $shelter))
            ->put(route('shelters.update', $shelter), [
                'camp_id' => $shelter->camp_id,
                'code' => $shelter->code,
                'type' => $shelter->type,
                'capacity' => 2,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('capacity');
    }

    public function test_aid_cannot_be_distributed_on_a_future_date(): void
    {
        $this->actingAsRole('aid_officer');
        $refugee = Refugee::factory()->create();
        $aidType = AidType::factory()->create();

        $this->from(route('aid.distributions.create'))
            ->post(route('aid.distributions.store'), [
                'aid_type_id' => $aidType->id,
                'refugee_id' => $refugee->id,
                'quantity' => 2,
                'distribution_date' => now()->addWeek()->toDateString(),
            ])
            ->assertSessionHasErrors('distribution_date');
    }

    public function test_a_movement_cannot_be_recorded_in_the_future(): void
    {
        $this->actingAsRole('security_officer');
        $checkpoint = Checkpoint::factory()->create();
        $refugee = Refugee::factory()->create(['current_camp_id' => $checkpoint->camp_id]);

        $this->from(route('security.movements.create'))
            ->post(route('security.movements.store'), [
                'refugee_id' => $refugee->id,
                'checkpoint_id' => $checkpoint->id,
                'movement_type' => 'exit',
                'movement_datetime' => now()->addDay()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('movement_datetime');
    }

    public function test_a_security_report_cannot_be_dated_in_the_future(): void
    {
        $this->actingAsRole('security_officer');
        $refugee = Refugee::factory()->create();

        $this->from(route('security.reports.create'))
            ->post(route('security.reports.store'), [
                'refugee_id' => $refugee->id,
                'incident_type' => 'شجار',
                'severity' => 'low',
                'report_date' => now()->addDay()->toDateString(),
                'description' => 'وصف',
            ])
            ->assertSessionHasErrors('report_date');
    }

    public function test_a_follow_up_cannot_precede_the_visit_it_follows(): void
    {
        $this->actingAsRole('medical_officer');
        $refugee = Refugee::factory()->create();
        $service = MedicalService::factory()->create();

        $this->from(route('medical.records.create'))
            ->post(route('medical.records.store'), [
                'refugee_id' => $refugee->id,
                'medical_service_id' => $service->id,
                'record_date' => today()->toDateString(),
                'diagnosis' => 'تشخيص',
                'needs_follow_up' => 1,
                'follow_up_date' => today()->subWeek()->toDateString(),
            ])
            ->assertSessionHasErrors('follow_up_date');
    }

    public function test_a_medical_record_cannot_be_dated_in_the_future(): void
    {
        $this->actingAsRole('medical_officer');
        $refugee = Refugee::factory()->create();
        $service = MedicalService::factory()->create();

        $this->from(route('medical.records.create'))
            ->post(route('medical.records.store'), [
                'refugee_id' => $refugee->id,
                'medical_service_id' => $service->id,
                'record_date' => today()->addDay()->toDateString(),
                'diagnosis' => 'تشخيص',
            ])
            ->assertSessionHasErrors('record_date');
    }

    public function test_a_duplicate_aid_type_for_the_same_organization_is_a_validation_error(): void
    {
        $this->actingAsRole('aid_officer');
        $aidType = AidType::factory()->create(['name' => 'سلة غذائية']);

        $this->from(route('aid.types.create'))
            ->post(route('aid.types.store'), [
                'organization_id' => $aidType->organization_id,
                'name' => 'سلة غذائية',
                'unit' => 'box',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_a_duplicate_checkpoint_name_in_the_same_camp_is_a_validation_error(): void
    {
        $this->actingAsRole('security_officer');
        $checkpoint = Checkpoint::factory()->create(['name' => 'البوابة الشمالية']);

        $this->from(route('security.checkpoints.create'))
            ->post(route('security.checkpoints.store'), [
                'camp_id' => $checkpoint->camp_id,
                'name' => 'البوابة الشمالية',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_a_new_user_must_be_given_a_strong_password(): void
    {
        $this->actingAsRole('admin');
        $role = Role::firstOrCreate(['name' => 'aid_officer'], ['display_name' => 'aid']);

        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'موظف',
                'email' => 'new@camp.local',
                'password' => 'password',
                'role_id' => $role->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_editing_a_user_without_a_password_keeps_the_existing_one(): void
    {
        $this->actingAsRole('admin');
        $user = User::factory()->role('aid_officer')->create();
        $originalHash = $user->password;

        $this->put(route('users.update', $user), [
            'name' => 'اسم جديد',
            'email' => $user->email,
            'password' => '',
            'role_id' => $user->role_id,
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertSame($originalHash, $user->refresh()->password);
        $this->assertSame('اسم جديد', $user->name);
    }
}
