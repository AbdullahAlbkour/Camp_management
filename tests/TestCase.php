<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Create a user carrying the given role name and authenticate as them.
     */
    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->role($role)->create();

        $this->actingAs($user);

        return $user;
    }
}
