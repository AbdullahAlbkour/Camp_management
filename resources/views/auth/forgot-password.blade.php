@extends('layouts.app')

@section('content')
<main class="login-screen">
    <section class="login-panel">
        <div class="brand login-brand">
            <span class="brand-mark"><i data-lucide="key-round"></i></span>
            <span>
                <strong>استعادة كلمة المرور</strong>
                <small>سنرسل رابط إعادة التعيين إلى بريدك</small>
            </span>
        </div>

        @include('layouts.flash')

        <form method="post" action="{{ route('password.email') }}" class="form-grid one">
            @csrf
            <label>
                البريد الإلكتروني
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>
            <button class="primary" type="submit"><i data-lucide="send"></i><span>إرسال الرابط</span></button>
        </form>

        <p class="muted small"><a href="{{ route('login') }}">العودة إلى تسجيل الدخول</a></p>
    </section>
</main>
@endsection
