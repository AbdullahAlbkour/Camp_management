<?php

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

/**
 * Single source of truth for the password policy, so every place that sets a
 * password (admin creation, self-service change, reset link) enforces the same bar.
 */
final class StrongPassword
{
    public static function rules(): Password
    {
        // The breach lookup is a live HTTP call, so it is skipped where there is no
        // network to rely on: tests would otherwise be both slow and flaky.
        return app()->runningUnitTests()
            ? self::offline()
            : self::offline()->uncompromised();
    }

    /**
     * The policy without the "have I been pwned" lookup.
     */
    public static function offline(): Password
    {
        return Password::min(10)->letters()->mixedCase()->numbers();
    }
}
