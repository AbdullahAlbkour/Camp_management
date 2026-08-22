<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Checkpoint;
use App\Models\EntryExitLog;
use App\Models\MedicalRecord;
use App\Models\Notification;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_due_follow_up_raises_a_notification(): void
    {
        MedicalRecord::factory()->needingFollowUp(today()->toDateString())->create();

        $this->artisan('camps:daily-digest')->assertSuccessful();

        $this->assertTrue(Notification::where('type', 'medical_follow_up_due')->exists());
    }

    public function test_an_overdue_follow_up_is_flagged_separately(): void
    {
        MedicalRecord::factory()->needingFollowUp(today()->subDays(5)->toDateString())->create();

        $this->artisan('camps:daily-digest')->assertSuccessful();

        $this->assertTrue(Notification::where('type', 'medical_follow_up_overdue')->exists());
        $this->assertFalse(Notification::where('type', 'medical_follow_up_due')->exists());
    }

    public function test_a_future_follow_up_is_not_flagged_yet(): void
    {
        MedicalRecord::factory()->needingFollowUp(today()->addDays(10)->toDateString())->create();

        $this->artisan('camps:daily-digest')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'like', 'medical_follow_up%')->count());
    }

    public function test_refugees_unhoused_past_the_threshold_are_reported(): void
    {
        Refugee::factory()->create([
            'housing_status' => 'unassigned',
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('camps:daily-digest --unassigned-days=3')->assertSuccessful();

        $this->assertTrue(Notification::where('type', 'housing_backlog')->exists());
    }

    public function test_a_recently_registered_unhoused_refugee_is_not_reported(): void
    {
        Refugee::factory()->create(['housing_status' => 'unassigned', 'created_at' => now()]);

        $this->artisan('camps:daily-digest --unassigned-days=3')->assertSuccessful();

        $this->assertFalse(Notification::where('type', 'housing_backlog')->exists());
    }

    public function test_a_long_absence_without_a_return_is_flagged(): void
    {
        $checkpoint = Checkpoint::factory()->create();
        $refugee = Refugee::factory()->create([
            'current_camp_id' => $checkpoint->camp_id,
            'presence_status' => 'outside',
        ]);

        EntryExitLog::factory()->create([
            'refugee_id' => $refugee->id,
            'camp_id' => $checkpoint->camp_id,
            'checkpoint_id' => $checkpoint->id,
            'movement_type' => 'exit',
            'movement_datetime' => now()->subDays(20),
        ]);

        $this->artisan('camps:daily-digest --absence-days=7')->assertSuccessful();

        $this->assertTrue(Notification::where('type', 'long_absence')->exists());
    }

    public function test_someone_who_came_back_is_not_flagged_as_absent(): void
    {
        $checkpoint = Checkpoint::factory()->create();
        $refugee = Refugee::factory()->create([
            'current_camp_id' => $checkpoint->camp_id,
            'presence_status' => 'outside',
        ]);

        foreach ([['exit', 20], ['entry', 1]] as [$type, $daysAgo]) {
            EntryExitLog::factory()->create([
                'refugee_id' => $refugee->id,
                'camp_id' => $checkpoint->camp_id,
                'checkpoint_id' => $checkpoint->id,
                'movement_type' => $type,
                'movement_datetime' => now()->subDays($daysAgo),
            ]);
        }

        $this->artisan('camps:daily-digest --absence-days=7')->assertSuccessful();

        $this->assertFalse(Notification::where('type', 'long_absence')->exists());
    }

    public function test_a_full_shelter_is_reported(): void
    {
        $shelter = Shelter::factory()->capacity(1)->create();
        Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create();

        $this->artisan('camps:daily-digest')->assertSuccessful();

        $this->assertTrue(Notification::where('type', 'shelter_full')->exists());
    }

    public function test_running_the_digest_twice_does_not_duplicate_notifications(): void
    {
        MedicalRecord::factory()->needingFollowUp(today()->toDateString())->create();

        $this->artisan('camps:daily-digest');
        $this->artisan('camps:daily-digest');

        $this->assertSame(
            2, // one per targeted role (medical_officer, admin), not four
            Notification::where('type', 'medical_follow_up_due')->count()
        );
    }

    public function test_pruning_removes_old_low_sensitivity_entries_only(): void
    {
        AuditLog::factory()->create(['sensitivity' => 'low', 'created_at' => now()->subDays(400)]);
        AuditLog::factory()->create(['sensitivity' => 'medium', 'created_at' => now()->subDays(400)]);
        $keptCritical = AuditLog::factory()->create(['sensitivity' => 'critical', 'created_at' => now()->subDays(400)]);
        $keptHigh = AuditLog::factory()->create(['sensitivity' => 'high', 'created_at' => now()->subDays(400)]);
        $keptRecent = AuditLog::factory()->create(['sensitivity' => 'low', 'created_at' => now()->subDays(5)]);

        $this->artisan('camps:prune-audit-logs --days=180')->assertSuccessful();

        $this->assertSame(3, AuditLog::count());
        foreach ([$keptCritical, $keptHigh, $keptRecent] as $kept) {
            $this->assertDatabaseHas('audit_logs', ['id' => $kept->id]);
        }
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        AuditLog::factory()->create(['sensitivity' => 'low', 'created_at' => now()->subDays(400)]);

        $this->artisan('camps:prune-audit-logs --days=180 --dry-run')->assertSuccessful();

        $this->assertSame(1, AuditLog::count());
    }
}
