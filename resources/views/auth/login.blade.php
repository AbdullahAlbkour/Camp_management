@extends('layouts.app')

@section('content')
<main class="login-screen">
    <section class="login-panel">
        <div class="brand login-brand">
            <span class="brand-mark"><i data-lucide="tent"></i></span>
            <span>
                <strong>إدارة مخيمات اللجوء</strong>
                <small>نظام ويب إداري محلي</small>
            </span>
        </div>

        @include('layouts.flash')

        <form method="post" action="{{ route('login.store') }}" class="form-grid one">
            @csrf
            <label>
                البريد الإلكتروني
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>
            <label>
                كلمة المرور
                <input type="password" name="password" required>
            </label>
            <label class="checkbox-row">
                <input type="checkbox" name="remember" value="1">
                <span>تذكرني</span>
            </label>
            <button class="primary" type="submit"><i data-lucide="log-in"></i><span>دخول</span></button>
        </form>

        <p class="muted small"><a href="{{ route('password.request') }}">نسيت كلمة المرور؟</a></p>

        <!--<p class="muted small">admin@camp.local / password</p>-->
    </section>
</main>
@endsection
