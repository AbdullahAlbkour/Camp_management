<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Refugee;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsAndAuditTest extends TestCase
{
    use RefreshDatabase;

    // ---- Notifications ----

    public function test_one_notification_is_raised_per_targeted_role(): void
    {
        app(NotificationService::class)->forRoles(
            ['housing_officer', 'admin'],
            'shelter_full',
            'وحدة ممتلئة',
        );

        $this->assertSame(2, Notification::count());
    }

    public function test_an_identical_notification_is_reopened_rather_than_duplicated(): void
    {
        $service = app(NotificationService::class);

        $service->forRoles(['admin'], 'shelter_full', 'وحدة ممتلئة', 'التفاصيل');
        Notification::query()->update(['status' => 'read']);
        $service->forRoles(['admin'], 'shelter_full', 'وحدة ممتلئة', 'التفاصيل');

        $this->assertSame(1, Notification::count());
        $this->assertSame('unread', Notification::first()->status);
    }

    public function test_a_resolved_notification_does_not_block_a_new_one(): void
    {
        $service = app(NotificationService::class);

        $service->forRoles(['admin'], 'shelter_full', 'وحدة ممتلئة');
        Notification::query()->update(['status' => 'resolved']);
        $service->forRoles(['admin'], 'shelter_full', 'وحدة ممتلئة');

        // The condition recurred after being dealt with, so it is raised again.
        $this->assertSame(2, Notification::count());
    }

    public function test_a_notification_targets_only_its_own_role(): void
    {
        app(NotificationService::class)->forRoles(['medical_officer'], 'medical_follow_up', 'متابعة طبية');

        $this->actingAsRole('housing_officer');
        $this->get(route('notifications.index'))->assertOk()->assertDontSee('متابعة طبية');

        $this->actingAsRole('medical_officer');
        $this->get(route('notifications.index'))->assertOk()->assertSee('متابعة طبية');
    }

    public function test_an_administrator_sees_notifications_for_every_role(): void
    {
        app(NotificationService::class)->forRoles(['medical_officer'], 'medical_follow_up', 'متابعة طبية');

        $this->actingAsRole('admin');
        $this->get(route('notifications.index'))->assertOk()->assertSee('متابعة طبية');
    }

    public function test_a_notification_can_be_marked_read_and_resolved(): void
    {
        $this->actingAsRole('admin');
        $notification = Notification::factory()->create(['status' => 'unread']);

        $this->post(route('notifications.read', $notification))->assertRedirect();
        $this->assertSame('read', $notification->refresh()->status);

        $this->post(route('notifications.resolve', $notification))->assertRedirect();
        $this->assertSame('resolved', $notification->refresh()->status);
    }

    // ---- Audit trail ----

    public function test_an_audit_entry_records_who_what_and_from_where(): void
    {
        $user = $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create();

        app(AuditLogService::class)->log('update', 'refugees', $refugee, 'تعديل', 'high', ['field' => 'value']);

        $entry = AuditLog::latest('id')->first();

        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame(Refugee::class, $entry->auditable_type);
        $this->assertSame($refugee->id, $entry->auditable_id);
        $this->assertSame('high', $entry->sensitivity);
        $this->assertNotNull($entry->ip_address);
        $this->assertSame(['field' => 'value'], $entry->metadata);
    }

    public function test_sensitive_values_are_stripped_from_audit_metadata(): void
    {
        $this->actingAsRole('admin');

        app(AuditLogService::class)->log('update', 'users', null, 'تعديل', 'critical', [
            'name' => 'موظف',
            'password' => 'super-secret',
            'password_confirmation' => 'super-secret',
            'diagnosis' => 'حالة طبية حساسة',
        ]);

        $metadata = AuditLog::latest('id')->first()->metadata;

        $this->assertArrayHasKey('name', $metadata);
        foreach (['password', 'password_confirmation', 'diagnosis'] as $secret) {
            $this->assertArrayNotHasKey($secret, $metadata);
        }
    }

    public function test_the_audit_screen_is_limited_to_admins_and_managers(): void
    {
        AuditLog::factory()->create(['description' => 'إجراء مسجل']);

        $this->actingAsRole('manager');
        $this->get(route('audit.index'))->assertOk()->assertSee('إجراء مسجل');

        $this->actingAsRole('aid_officer');
        $this->get(route('audit.index'))->assertForbidden();
    }

    public function test_creating_a_user_is_recorded_as_critical(): void
    {
        $this->actingAsRole('admin');
        $role = User::factory()->role('aid_officer')->create()->role;

        $this->post(route('users.store'), [
            'name' => 'موظف جديد',
            'email' => 'new@camp.local',
            'password' => 'Strong-Passw0rd-9',
            'password_confirmation' => 'Strong-Passw0rd-9',
            'role_id' => $role->id,
            'status' => 'active',
        ])->assertRedirect();

        $this->assertTrue(
            AuditLog::where('module', 'users')->where('sensitivity', 'critical')->exists()
        );
    }
}
