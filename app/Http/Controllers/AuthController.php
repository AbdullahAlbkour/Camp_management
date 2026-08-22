<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\LoginThrottleService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginThrottleService $throttle,
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications,
    ) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->throttle->ensureIsNotLocked($request);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return $this->handleFailedAttempt($request, $credentials['email']);
        }

        if (! $request->user()->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'هذا الحساب غير فعال.'])->onlyInput('email');
        }

        $this->throttle->clear($request);
        $request->session()->regenerate();

        $this->auditLog->log('login', 'auth', $request->user(), 'تسجيل دخول المستخدم', 'medium');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $this->auditLog->log('logout', 'auth', $user, 'تسجيل خروج المستخدم', 'low');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Log the failed attempt, warn administrators once the account locks, and bounce back.
     */
    private function handleFailedAttempt(Request $request, string $email): RedirectResponse
    {
        $lockedOut = $this->throttle->recordFailure($request);

        $this->auditLog->log('login_failed', 'auth', null, 'محاولة دخول فاشلة للبريد '.$email, 'high', [
            'email' => $email,
        ]);

        if ($lockedOut) {
            $this->notifications->forRoles(
                ['admin'],
                'login_lockout',
                'إيقاف مؤقت لمحاولات الدخول',
                'تم تجاوز عدد محاولات الدخول للبريد '.$email.' من العنوان '.$request->ip().'.',
            );

            return back()->withErrors([
                'email' => 'تم تجاوز عدد محاولات الدخول المسموح بها. حاول مرة أخرى لاحقًا.',
            ])->onlyInput('email');
        }

        return back()->withErrors([
            'email' => 'بيانات الدخول غير صحيحة. المحاولات المتبقية: '.$this->throttle->remainingAttempts($request).'.',
        ])->onlyInput('email');
    }
}
