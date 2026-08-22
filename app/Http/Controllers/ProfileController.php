<?php

namespace App\Http\Controllers;

use App\Rules\StrongPassword;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user()->load('role'),
        ]);
    }

    public function update(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);
        $auditLog->log('update', 'profile', $user, 'تعديل الملف الشخصي', 'medium', $data);

        return redirect()->route('profile.edit')->with('success', 'تم تحديث بياناتك.');
    }

    public function updatePassword(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', StrongPassword::rules()],
        ], [
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
            'password.different' => 'كلمة المرور الجديدة يجب أن تختلف عن الحالية.',
        ]);

        $user->update(['password' => Hash::make($request->string('password')->value())]);

        // Other sessions keep working off the old credentials otherwise.
        Auth::logoutOtherDevices($request->string('password')->value());
        $request->session()->regenerate();

        $auditLog->log('password_change', 'profile', $user, 'تغيير كلمة المرور الذاتية', 'critical');

        return redirect()->route('profile.edit')->with('success', 'تم تغيير كلمة المرور وإنهاء الجلسات الأخرى.');
    }
}
