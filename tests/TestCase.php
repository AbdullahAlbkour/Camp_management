<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Create a user carrying the given role name and authenticate as them.
     *
     * The session is flushed first: AuthenticateSession pins a session to the
     * password hash of the user who opened it, so switching users inside one test
     * without a fresh session would be treated as a hijacked session and logged out.
     */
    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->role($role)->create();

        $this->flushSession();
        $this->actingAs($user);

        return $user;
    }
}
