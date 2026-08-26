<?php

namespace Tests\Feature;

use App\Models\AidType;
use App\Models\Camp;
use App\Models\Checkpoint;
use App\Models\Household;
use App\Models\MedicalService;
use App\Models\Notification;
use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use App\Models\Shelter;
use App\Services\AidDistributionService;
use App\Services\MedicalRecordService;
use App\Services\MovementSecurityService;
use App\Services\RefugeeRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DomainServicesTest extends TestCase
{
    use RefreshDatabase;

    // ---- Registration ----

    public function test_registering_a_refugee_writes_an_initial_residency_entry(): void
    {
        $camp = Camp::factory()->create();

        $refugee = app(RefugeeRegistrationService::class)->register([
            'first_name' => 'خالد',
            'last_name' => 'الحسن',
            'gender' => 'male',
            'current_camp_id' => $camp->id,
        ]);

        $transfer = ResidencyTransfer::where('refugee_id', $refugee->id)->first();

        $this->assertSame('initial', $transfer->transfer_type);
        $this->assertSame('unassigned', $refugee->housing_status);
        $this->assertSame('inside', $refugee->presence_status);
    }

    public function test_registering_into_a_full_shelter_is_refused(): void
    {
        $shelter = Shelter::factory()->capacity(1)->create();
        Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create();

        $this->expectException(ValidationException::class);

        app(RefugeeRegistrationService::class)->register([
            'first_name' => 'خالد',
            'last_name' => 'الحسن',
            'gender' => 'male',
            'current_camp_id' => $shelter->camp_id,
            'current_shelter_id' => $shelter->id,
        ]);
    }

    public function test_an_unhoused_registration_notifies_the_housing_team(): void
    {
        $camp = Camp::factory()->create();

        app(RefugeeRegistrationService::class)->register([
            'first_name' => 'خالد',
            'last_name' => 'الحسن',
            'gender' => 'male',
            'current_camp_id' => $camp->id,
        ]);

        $this->assertTrue(Notification::where('type', 'housing_unassigned')->exists());
    }

    public function test_a_matching_document_number_is_reported_as_a_possible_duplicate(): void
    {
        Refugee::factory()->create(['document_number' => 'DOC-777']);

        $duplicates = app(RefugeeRegistrationService::class)->possibleDuplicates([
            'document_number' => 'DOC-777',
            'first_name' => 'مختلف',
            'last_name' => 'تمامًا',
        ]);

        $this->assertCount(1, $duplicates);
        $this->assertContains('رقم الوثيقة مطابق', $duplicates->first()->match_reasons);
    }

    public function test_a_shared_first_name_alone_is_not_treated_as_a_duplicate(): void
    {
        // In a camp of thousands a common given name matches almost everyone; a
        // warning that always fires is a warning officers learn to click past.
        Refugee::factory()->create(['first_name' => 'محمد', 'last_name' => 'العلي']);

        $duplicates = app(RefugeeRegistrationService::class)->possibleDuplicates([
            'first_name' => 'محمد',
            'last_name' => 'الخطيب',
        ]);

        $this->assertCount(0, $duplicates);
    }

    public function test_a_matching_full_name_is_reported(): void
    {
        Refugee::factory()->create(['first_name' => 'محمد', 'last_name' => 'العلي']);

        $duplicates = app(RefugeeRegistrationService::class)->possibleDuplicates([
            'first_name' => 'محمد',
            'last_name' => 'العلي',
        ]);

        $this->assertCount(1, $duplicates);
    }

    public function test_stronger_evidence_is_ranked_first(): void
    {
        Refugee::factory()->create([
            'first_name' => 'محمد',
            'last_name' => 'العلي',
            'document_number' => 'DOC-OTHER',
        ]);
        Refugee::factory()->create([
            'first_name' => 'سالم',
            'last_name' => 'الخطيب',
            'document_number' => 'DOC-777',
        ]);

        $duplicates = app(RefugeeRegistrationService::class)->possibleDuplicates([
            'first_name' => 'محمد',
            'last_name' => 'العلي',
            'document_number' => 'DOC-777',
        ]);

        $this->assertCount(2, $duplicates);
        $this->assertSame('DOC-777', $duplicates->first()->document_number);
    }

    public function test_a_registration_with_no_identifying_data_reports_nothing(): void
    {
        Refugee::factory()->count(3)->create();

        $this->assertCount(0, app(RefugeeRegistrationService::class)->possibleDuplicates([]));
    }

    public function test_the_registration_screen_warns_before_creating_a_likely_duplicate(): void
    {
        $this->actingAsRole('registration_officer');
        $existing = Refugee::factory()->create([
            'first_name' => 'خالد',
            'last_name' => 'الحسن',
        ]);

        $this->from(route('refugees.create'))
            ->post(route('refugees.store'), [
                'first_name' => 'خالد',
                'last_name' => 'الحسن',
                'gender' => 'male',
                'document_number' => 'DOC-888',
                'current_camp_id' => $existing->current_camp_id,
            ])
            ->assertSessionHas('warning');

        $this->assertSame(1, Refugee::count(), 'Nothing should be created until the officer confirms.');
    }

    public function test_confirming_the_duplicate_check_creates_the_record(): void
    {
        $this->actingAsRole('registration_officer');
        $existing = Refugee::factory()->create();

        $this->post(route('refugees.store'), [
            'first_name' => 'خالد',
            'last_name' => 'الحسن',
            'gender' => 'male',
            'current_camp_id' => $existing->current_camp_id,
            'confirmed_duplicate_check' => 1,
        ])->assertRedirect();

        $this->assertSame(2, Refugee::count());
    }

    public function test_the_movement_form_offers_checkpoints_from_every_camp(): void
    {
        $this->actingAsRole('security_officer');
        $here = Camp::factory()->create(['name' => 'مخيم السلام']);
        $elsewhere = Camp::factory()->create(['name' => 'مخيم النور']);
        Checkpoint::factory()->create(['camp_id' => $here->id, 'name' => 'البوابة الشمالية']);
        Checkpoint::factory()->create(['camp_id' => $elsewhere->id, 'name' => 'البوابة الجنوبية']);

        $this->get(route('security.movements.create'))
            ->assertOk()
            ->assertSee('البوابة الشمالية')
            ->assertSee('البوابة الجنوبية')
            // Grouped under the camp rather than repeated on every row.
            ->assertSee('<optgroup label="مخيم السلام">', false)
            ->assertSee('<optgroup label="مخيم النور">', false);
    }

    public function test_a_closed_checkpoint_is_still_offered_but_marked(): void
    {
        // Hiding it would make an older movement impossible to record after the
        // gate is closed.
        $this->actingAsRole('security_officer');
        Checkpoint::factory()->create(['name' => 'البوابة القديمة', 'status' => 'inactive']);

        $this->get(route('security.movements.create'))
            ->assertOk()
            ->assertSee('البوابة القديمة (غير فعالة)');
    }

    public function test_recording_a_cross_camp_movement_through_the_screen_succeeds(): void
    {
        // The block used to be raised inside the service, so the form accepted
        // the choice and then rejected the save. This covers the whole path.
        $this->actingAsRole('security_officer');
        $checkpoint = Checkpoint::factory()->create();
        $refugee = Refugee::factory()->create();

        $this->assertNotSame($checkpoint->camp_id, $refugee->current_camp_id);

        $this->from(route('security.movements.create'))
            ->post(route('security.movements.store'), [
                'refugee_id' => $refugee->id,
                'checkpoint_id' => $checkpoint->id,
                'movement_type' => 'exit',
                'movement_datetime' => now()->toDateTimeString(),
            ])
            ->assertRedirect(route('security.movements'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('entry_exit_logs', [
            'refugee_id' => $refugee->id,
            'checkpoint_id' => $checkpoint->id,
            'camp_id' => $checkpoint->camp_id,
        ]);
    }

    // ---- Aid ----

    public function test_aid_must_target_exactly_one_beneficiary(): void
    {
        $refugee = Refugee::factory()->create();
        $household = Household::factory()->create();
        $aidType = AidType::factory()->create();

        $this->expectException(ValidationException::class);

        app(AidDistributionService::class)->distribute([
            'aid_type_id' => $aidType->id,
            'refugee_id' => $refugee->id,
            'household_id' => $household->id,
            'quantity' => 1,
            'distribution_date' => today()->toDateString(),
        ]);
    }

    public function test_aid_with_no_beneficiary_is_refused(): void
    {
        $aidType = AidType::factory()->create();

        $this->expectException(ValidationException::class);

        app(AidDistributionService::class)->distribute([
            'aid_type_id' => $aidType->id,
            'quantity' => 1,
            'distribution_date' => today()->toDateString(),
        ]);
    }

    public function test_the_camp_is_inferred_from_the_beneficiary(): void
    {
        $refugee = Refugee::factory()->create();
        $aidType = AidType::factory()->create();

        $distribution = app(AidDistributionService::class)->distribute([
            'aid_type_id' => $aidType->id,
            'refugee_id' => $refugee->id,
            'quantity' => 3,
            'distribution_date' => today()->toDateString(),
        ]);

        $this->assertSame($refugee->current_camp_id, $distribution->camp_id);
    }

    public function test_a_repeat_distribution_within_thirty_days_raises_a_duplicate_warning(): void
    {
        $refugee = Refugee::factory()->create();
        $aidType = AidType::factory()->create();
        $service = app(AidDistributionService::class);

        $payload = [
            'aid_type_id' => $aidType->id,
            'refugee_id' => $refugee->id,
            'quantity' => 1,
            'distribution_date' => today()->toDateString(),
        ];

        $service->distribute($payload);
        $this->assertFalse(Notification::where('type', 'aid_duplicate_warning')->exists());

        $service->distribute($payload);
        $this->assertTrue(Notification::where('type', 'aid_duplicate_warning')->exists());
    }

    // ---- Medical ----

    public function test_a_follow_up_without_a_date_is_refused(): void
    {
        $refugee = Refugee::factory()->create();
        $service = MedicalService::factory()->create();

        $this->expectException(ValidationException::class);

        app(MedicalRecordService::class)->create([
            'refugee_id' => $refugee->id,
            'medical_service_id' => $service->id,
            'record_date' => today()->toDateString(),
            'diagnosis' => 'تشخيص',
            'needs_follow_up' => true,
        ]);
    }

    public function test_a_medical_record_inherits_the_refugees_current_camp(): void
    {
        $refugee = Refugee::factory()->create();
        $service = MedicalService::factory()->create();

        $record = app(MedicalRecordService::class)->create([
            'refugee_id' => $refugee->id,
            'medical_service_id' => $service->id,
            'record_date' => today()->toDateString(),
            'diagnosis' => 'تشخيص',
        ]);

        $this->assertSame($refugee->current_camp_id, $record->camp_id);
    }

    // ---- Movement and security ----

    public function test_a_movement_can_be_recorded_at_another_camps_checkpoint(): void
    {
        // People do cross between camps. Refusing to log a passage that happened
        // leaves a gap in the record rather than preventing anything.
        $checkpoint = Checkpoint::factory()->create();
        $refugee = Refugee::factory()->create();

        $movement = app(MovementSecurityService::class)->recordMovement([
            'refugee_id' => $refugee->id,
            'checkpoint_id' => $checkpoint->id,
            'movement_type' => 'exit',
            'movement_datetime' => now()->toDateTimeString(),
        ]);

        $this->assertSame($checkpoint->id, $movement->checkpoint_id);
    }

    public function test_a_movement_is_filed_under_the_camp_of_the_gate(): void
    {
        // The row names a checkpoint; a camp column holding a different camp
        // would contradict it. The movement happened where the gate is.
        $checkpoint = Checkpoint::factory()->create();
        $refugee = Refugee::factory()->create();

        $this->assertNotSame($checkpoint->camp_id, $refugee->current_camp_id);

        $movement = app(MovementSecurityService::class)->recordMovement([
            'refugee_id' => $refugee->id,
            'checkpoint_id' => $checkpoint->id,
            'movement_type' => 'entry',
            'movement_datetime' => now()->toDateTimeString(),
        ]);

        $this->assertSame($checkpoint->camp_id, $movement->camp_id);
    }

    public function test_crossing_into_another_camp_does_not_relocate_the_refugee(): void
    {
        // Residence changes go through HousingService, which enforces capacity
        // and writes the transfer history. A gate reading must not bypass it.
        $checkpoint = Checkpoint::factory()->create();
        $refugee = Refugee::factory()->create();
        $originalCamp = $refugee->current_camp_id;

        app(MovementSecurityService::class)->recordMovement([
            'refugee_id' => $refugee->id,
            'checkpoint_id' => $checkpoint->id,
            'movement_type' => 'entry',
            'movement_datetime' => now()->toDateTimeString(),
        ]);

        $refugee->refresh();

        $this->assertSame($originalCamp, $refugee->current_camp_id);
        $this->assertSame('inside', $refugee->presence_status);
        $this->assertSame(0, $refugee->residencyTransfers()->count());
    }

    public function test_an_exit_marks_the_refugee_as_outside_without_moving_them(): void
    {
        $checkpoint = Checkpoint::factory()->create();
        $shelter = Shelter::factory()->capacity(3)->create(['camp_id' => $checkpoint->camp_id]);
        $refugee = Refugee::factory()->inShelter($shelter->id, $checkpoint->camp_id)->create();

        app(MovementSecurityService::class)->recordMovement([
            'refugee_id' => $refugee->id,
            'checkpoint_id' => $checkpoint->id,
            'movement_type' => 'exit',
            'movement_datetime' => now()->toDateTimeString(),
        ]);

        $refugee->refresh();

        $this->assertSame('outside', $refugee->presence_status);
        // Leaving the camp is not a move: the housing assignment is untouched.
        $this->assertSame($shelter->id, $refugee->current_shelter_id);
        $this->assertSame($checkpoint->camp_id, $refugee->current_camp_id);
    }

    public function test_a_high_severity_incident_notifies_the_security_team(): void
    {
        $refugee = Refugee::factory()->create();

        app(MovementSecurityService::class)->createSecurityReport([
            'refugee_id' => $refugee->id,
            'incident_type' => 'شجار',
            'severity' => 'critical',
            'report_date' => today()->toDateString(),
            'description' => 'وصف الحادثة',
        ]);

        $this->assertTrue(Notification::where('type', 'security_high_risk')->exists());
    }

    public function test_a_low_severity_incident_does_not_notify(): void
    {
        $refugee = Refugee::factory()->create();

        app(MovementSecurityService::class)->createSecurityReport([
            'refugee_id' => $refugee->id,
            'incident_type' => 'ملاحظة',
            'severity' => 'low',
            'report_date' => today()->toDateString(),
            'description' => 'وصف',
        ]);

        $this->assertFalse(Notification::where('type', 'security_high_risk')->exists());
    }
}
