<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        if (! $request->user()->isActive()) {
            Auth::logout();

            return back()->withErrors(['email' => 'هذا الحساب غير فعال.'])->onlyInput('email');
        }

        $auditLog->log('login', 'auth', $request->user(), 'تسجيل دخول المستخدم', 'medium');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $auditLog->log('logout', 'auth', $user, 'تسجيل خروج المستخدم', 'low');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
