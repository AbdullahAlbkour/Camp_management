<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_is_sent_to_a_known_address(): void
    {
        Notification::fake();

        $user = User::factory()->role('admin')->create(['email' => 'admin@camp.local']);

        $this->post(route('password.email'), ['email' => 'admin@camp.local'])
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_address_gets_the_same_answer(): void
    {
        Notification::fake();

        // No account enumeration: the response must not differ from the known-address case.
        $this->post(route('password.email'), ['email' => 'nobody@camp.local'])
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        Notification::fake();

        $user = User::factory()->role('admin')->create([
            'email' => 'admin@camp.local',
            'password' => Hash::make('Old-Passw0rd-1'),
        ]);

        $this->post(route('password.email'), ['email' => 'admin@camp.local']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'admin@camp.local',
            'password' => 'Brand-New-Passw0rd',
            'password_confirmation' => 'Brand-New-Passw0rd',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Brand-New-Passw0rd', $user->refresh()->password));
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        User::factory()->role('admin')->create(['email' => 'admin@camp.local']);

        $this->from(route('password.reset', 'bogus'))
            ->post(route('password.update'), [
                'token' => 'bogus-token',
                'email' => 'admin@camp.local',
                'password' => 'Brand-New-Passw0rd',
                'password_confirmation' => 'Brand-New-Passw0rd',
            ])
            ->assertSessionHasErrors('email');
    }
}
