<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The role matrix, asserted end to end through the routes.
 *
 * Every module screen is checked for one role that should reach it and the roles
 * that should not, so a widened middleware list cannot pass unnoticed.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function moduleMatrix(): array
    {
        return [
            'users' => ['users.index', 'admin', ['manager', 'registration_officer', 'housing_officer', 'aid_officer', 'medical_officer', 'security_officer']],
            'camps' => ['camps.index', 'housing_officer', ['registration_officer', 'aid_officer', 'medical_officer', 'security_officer', 'manager']],
            'shelters' => ['shelters.index', 'housing_officer', ['registration_officer', 'aid_officer', 'medical_officer', 'security_officer', 'manager']],
            'housing' => ['housing.unassigned', 'housing_officer', ['registration_officer', 'aid_officer', 'medical_officer', 'security_officer', 'manager']],
            'aid distributions' => ['aid.distributions', 'aid_officer', ['registration_officer', 'housing_officer', 'medical_officer', 'security_officer', 'manager']],
            'aid organizations' => ['aid.organizations', 'aid_officer', ['registration_officer', 'housing_officer', 'medical_officer', 'security_officer', 'manager']],
            'medical records' => ['medical.records', 'medical_officer', ['registration_officer', 'housing_officer', 'aid_officer', 'security_officer', 'manager']],
            'medical services' => ['medical.services', 'medical_officer', ['registration_officer', 'housing_officer', 'aid_officer', 'security_officer', 'manager']],
            'security movements' => ['security.movements', 'security_officer', ['registration_officer', 'housing_officer', 'aid_officer', 'medical_officer', 'manager']],
            'security reports' => ['security.reports', 'security_officer', ['registration_officer', 'housing_officer', 'aid_officer', 'medical_officer', 'manager']],
            'checkpoints' => ['security.checkpoints', 'security_officer', ['registration_officer', 'housing_officer', 'aid_officer', 'medical_officer', 'manager']],
            'audit log' => ['audit.index', 'manager', ['registration_officer', 'housing_officer', 'aid_officer', 'medical_officer', 'security_officer']],
        ];
    }

    /**
     * @param  list<string>  $deniedRoles
     */
    #[DataProvider('moduleMatrix')]
    public function test_the_module_is_reachable_only_by_its_own_roles(string $route, string $allowedRole, array $deniedRoles): void
    {
        $this->actingAsRole($allowedRole);
        $this->get(route($route))->assertOk();

        foreach ($deniedRoles as $role) {
            $this->actingAsRole($role);
            $this->get(route($route))->assertForbidden("Role {$role} should not reach {$route}.");
        }
    }

    /**
     * @param  list<string>  $deniedRoles
     */
    #[DataProvider('moduleMatrix')]
    public function test_an_administrator_reaches_every_module(string $route, string $allowedRole, array $deniedRoles): void
    {
        $this->actingAsRole('admin');

        $this->get(route($route))->assertOk();
    }

    public function test_a_deactivated_user_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->role('manager')->create();

        $this->actingAs($user);
        $this->get(route('dashboard'))->assertOk();

        $user->update(['status' => 'inactive']);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_every_module_screen_requires_authentication(): void
    {
        foreach (self::moduleMatrix() as [$route]) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_shared_screens_are_open_to_any_signed_in_role(): void
    {
        foreach (['registration_officer', 'housing_officer', 'aid_officer', 'medical_officer', 'security_officer', 'manager'] as $role) {
            $this->actingAsRole($role);

            $this->get(route('dashboard'))->assertOk();
            $this->get(route('refugees.index'))->assertOk();
            $this->get(route('households.index'))->assertOk();
            $this->get(route('notifications.index'))->assertOk();
            $this->get(route('reports.index'))->assertOk();
            $this->get(route('profile.edit'))->assertOk();
        }
    }

    public function test_only_registration_roles_may_create_a_refugee(): void
    {
        $this->actingAsRole('registration_officer');
        $this->get(route('refugees.create'))->assertOk();

        foreach (['housing_officer', 'aid_officer', 'medical_officer', 'security_officer', 'manager'] as $role) {
            $this->actingAsRole($role);
            $this->get(route('refugees.create'))->assertForbidden();
        }
    }
}
