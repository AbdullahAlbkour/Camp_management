<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_change_their_own_password(): void
    {
        $user = User::factory()->role('aid_officer')->create([
            'password' => Hash::make('Old-Passw0rd-1'),
        ]);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'Old-Passw0rd-1',
                'password' => 'Brand-New-Passw0rd',
                'password_confirmation' => 'Brand-New-Passw0rd',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('Brand-New-Passw0rd', $user->refresh()->password));
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->role('aid_officer')->create([
            'password' => Hash::make('Old-Passw0rd-1'),
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password'), [
                'current_password' => 'not-the-password',
                'password' => 'Brand-New-Passw0rd',
                'password_confirmation' => 'Brand-New-Passw0rd',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Old-Passw0rd-1', $user->refresh()->password));
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $user = User::factory()->role('aid_officer')->create([
            'password' => Hash::make('Old-Passw0rd-1'),
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password'), [
                'current_password' => 'Old-Passw0rd-1',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_a_user_can_update_their_name_and_email(): void
    {
        $user = User::factory()->role('manager')->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'مدير المخيم',
                'email' => 'manager-updated@camp.local',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('مدير المخيم', $user->name);
        $this->assertSame('manager-updated@camp.local', $user->email);
    }

    public function test_the_profile_page_renders(): void
    {
        $this->actingAsRole('manager');

        $this->get(route('profile.edit'))->assertOk()->assertSee('تغيير كلمة المرور');
    }
}
