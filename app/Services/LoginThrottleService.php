<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Brute-force protection for the login form.
 *
 * Attempts are counted per (email + IP) pair so one attacker cannot lock every
 * account out by hammering a single address from many machines, nor lock a whole
 * office out by hammering one account.
 */
class LoginThrottleService
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    /**
     * Abort with a validation error while the throttle key is exhausted.
     */
    public function ensureIsNotLocked(Request $request): void
    {
        $key = $this->key($request);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => $this->lockoutMessage(RateLimiter::availableIn($key)),
        ]);
    }

    /**
     * Record a failed attempt and report whether that attempt exhausted the allowance.
     */
    public function recordFailure(Request $request): bool
    {
        $key = $this->key($request);
        RateLimiter::hit($key, self::DECAY_SECONDS);

        return RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS);
    }

    public function clear(Request $request): void
    {
        RateLimiter::clear($this->key($request));
    }

    public function remainingAttempts(Request $request): int
    {
        return RateLimiter::remaining($this->key($request), self::MAX_ATTEMPTS);
    }

    private function key(Request $request): string
    {
        return 'login:'.Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip();
    }

    private function lockoutMessage(int $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);

        return 'تم تجاوز عدد محاولات الدخول المسموح بها. حاول مرة أخرى بعد '.$minutes.' دقيقة.';
    }
}
