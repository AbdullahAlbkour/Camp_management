<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_user_can_log_in(): void
    {
        $user = User::factory()->role('admin')->create([
            'email' => 'admin@camp.local',
            'password' => Hash::make('Secret-Passw0rd'),
        ]);

        $this->post(route('login.store'), [
            'email' => 'admin@camp.local',
            'password' => 'Secret-Passw0rd',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_inactive_user_cannot_log_in(): void
    {
        User::factory()->role('admin')->inactive()->create([
            'email' => 'blocked@camp.local',
            'password' => Hash::make('Secret-Passw0rd'),
        ]);

        $this->post(route('login.store'), [
            'email' => 'blocked@camp.local',
            'password' => 'Secret-Passw0rd',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_repeated_failures_lock_the_login_form(): void
    {
        User::factory()->role('admin')->create([
            'email' => 'admin@camp.local',
            'password' => Hash::make('Secret-Passw0rd'),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'admin@camp.local',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        // The 6th attempt is refused even though the credentials are now correct.
        $this->post(route('login.store'), [
            'email' => 'admin@camp.local',
            'password' => 'Secret-Passw0rd',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_failed_attempts_are_written_to_the_audit_trail(): void
    {
        $this->post(route('login.store'), [
            'email' => 'ghost@camp.local',
            'password' => 'wrong-password',
        ]);

        $this->assertTrue(
            AuditLog::where('action', 'login_failed')->where('sensitivity', 'high')->exists()
        );
    }

    public function test_security_headers_are_present_on_responses(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
