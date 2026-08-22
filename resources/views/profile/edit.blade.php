@extends('layouts.app', ['title' => 'الملف الشخصي'])

@section('content')
<section class="toolbar">
    <div>
        <h2>الملف الشخصي</h2>
        <p>{{ $user->role?->display_name }}</p>
    </div>
</section>

<div class="panel-grid">
    <section class="panel">
        <h3>البيانات الأساسية</h3>
        <form method="post" action="{{ route('profile.update') }}" class="form-grid one">
            @csrf
            @method('PUT')
            <label>
                الاسم
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
            </label>
            <label>
                البريد الإلكتروني
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
            </label>
            <div class="form-actions">
                <button class="primary" type="submit"><i data-lucide="save"></i><span>حفظ</span></button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h3>تغيير كلمة المرور</h3>
        <p class="muted small">تغيير كلمة المرور ينهي جميع جلساتك الأخرى على الأجهزة الأخرى.</p>
        <form method="post" action="{{ route('profile.password') }}" class="form-grid one">
            @csrf
            @method('PUT')
            <label>
                كلمة المرور الحالية
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label>
                كلمة المرور الجديدة
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label>
                تأكيد كلمة المرور الجديدة
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>
            <div class="form-actions">
                <button class="primary" type="submit"><i data-lucide="key-round"></i><span>تغيير</span></button>
            </div>
        </form>
    </section>
</div>
@endsection
