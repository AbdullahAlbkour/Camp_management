@extends('layouts.app')

@section('content')
<main class="login-screen">
    <section class="login-panel">
        <div class="brand login-brand">
            <span class="brand-mark"><i data-lucide="shield-check"></i></span>
            <span>
                <strong>تعيين كلمة مرور جديدة</strong>
                <small>10 أحرف على الأقل مع حروف كبيرة وصغيرة وأرقام</small>
            </span>
        </div>

        @include('layouts.flash')

        <form method="post" action="{{ route('password.update') }}" class="form-grid one">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label>
                البريد الإلكتروني
                <input type="email" name="email" value="{{ old('email', $email) }}" required>
            </label>
            <label>
                كلمة المرور الجديدة
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label>
                تأكيد كلمة المرور
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>
            <button class="primary" type="submit"><i data-lucide="save"></i><span>حفظ كلمة المرور</span></button>
        </form>
    </section>
</main>
@endsection
