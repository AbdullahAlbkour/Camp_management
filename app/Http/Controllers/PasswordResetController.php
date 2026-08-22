<?php

namespace App\Http\Controllers;

use App\Rules\StrongPassword;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showLinkRequest(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // The status is deliberately not branched on: telling an anonymous visitor whether
        // an address exists would turn this form into an account enumeration oracle.
        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'إذا كان البريد مسجلًا لدينا فسيصلك رابط إعادة التعيين خلال دقائق.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', StrongPassword::rules()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($auditLog): void {
                $user->forceFill([
                    'password' => Hash::make(request()->string('password')->value()),
                    'remember_token' => Str::random(60),
                ])->save();

                $auditLog->log('password_reset', 'auth', $user, 'إعادة تعيين كلمة المرور عبر رابط', 'critical');

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية.']);
        }

        return redirect()->route('login')->with('success', 'تم تعيين كلمة المرور. يمكنك الدخول الآن.');
    }
}
