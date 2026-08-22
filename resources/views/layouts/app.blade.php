<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    @stack('head')
    <script defer src="https://unpkg.com/lucide@latest"></script>
    <script defer src="{{ asset('assets/app.js') }}"></script>
</head>
<body>
@auth
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-head">
                <a class="brand" href="{{ route('dashboard') }}">
                    <span class="brand-mark"><i data-lucide="tent"></i></span>
                    <span>
                        <strong>إدارة المخيمات</strong>
                        <small>{{ auth()->user()->role?->display_name }}</small>
                    </span>
                </a>
                <button class="sidebar-collapse" type="button" data-sidebar-collapse aria-expanded="true" title="تصغير القائمة">
                    <span class="collapse-open"><i data-lucide="chevrons-right"></i></span>
                    <span class="collapse-closed"><i data-lucide="chevrons-left"></i></span>
                </button>
            </div>

            <nav class="nav">
                <a @class(['active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}"><i data-lucide="layout-dashboard"></i><span>لوحة التحكم</span></a>
                @role('admin')<a @class(['active' => request()->routeIs('users.*')]) href="{{ route('users.index') }}"><i data-lucide="users-round"></i><span>المستخدمون</span></a>@endrole
                @role('admin','housing_officer')<a @class(['active' => request()->routeIs('camps.*')]) href="{{ route('camps.index') }}"><i data-lucide="map"></i><span>المخيمات</span></a>@endrole
                @role('admin','housing_officer')<a @class(['active' => request()->routeIs('shelters.*')]) href="{{ route('shelters.index') }}"><i data-lucide="home"></i><span>الوحدات السكنية</span></a>@endrole
                <a @class(['active' => request()->routeIs('refugees.*')]) href="{{ route('refugees.index') }}"><i data-lucide="search"></i><span>اللاجئون</span></a>
                <a @class(['active' => request()->routeIs('households.*')]) href="{{ route('households.index') }}"><i data-lucide="house"></i><span>الأسر</span></a>
                @role('admin','housing_officer')<a @class(['active' => request()->routeIs('housing.*')]) href="{{ route('housing.unassigned') }}"><i data-lucide="bed"></i><span>السكن والانتقالات</span></a>@endrole
                @role('admin','aid_officer')<a @class(['active' => request()->routeIs('aid.distributions*')]) href="{{ route('aid.distributions') }}"><i data-lucide="package-check"></i><span>المساعدات</span></a>@endrole
                @role('admin','aid_officer')<a @class(['active' => request()->routeIs('aid.types*')]) href="{{ route('aid.types') }}"><i data-lucide="tags"></i><span>أنواع المساعدات</span></a>@endrole
                @role('admin','aid_officer')<a @class(['active' => request()->routeIs('aid.organizations*')]) href="{{ route('aid.organizations') }}"><i data-lucide="handshake"></i><span>الجهات الداعمة</span></a>@endrole
                @role('admin','medical_officer')<a @class(['active' => request()->routeIs('medical.records*')]) href="{{ route('medical.records') }}"><i data-lucide="stethoscope"></i><span>السجلات الطبية</span></a>@endrole
                @role('admin','medical_officer')<a @class(['active' => request()->routeIs('medical.services*')]) href="{{ route('medical.services') }}"><i data-lucide="activity"></i><span>الخدمات الطبية</span></a>@endrole
                @role('admin','security_officer')<a @class(['active' => request()->routeIs('security.movements*')]) href="{{ route('security.movements') }}"><i data-lucide="shield-check"></i><span>الأمن والحركة</span></a>@endrole
                @role('admin','security_officer')<a @class(['active' => request()->routeIs('security.reports*')]) href="{{ route('security.reports') }}"><i data-lucide="shield-alert"></i><span>التقارير الأمنية</span></a>@endrole
                @role('admin','security_officer')<a @class(['active' => request()->routeIs('security.checkpoints*')]) href="{{ route('security.checkpoints') }}"><i data-lucide="scan-line"></i><span>نقاط التفتيش</span></a>@endrole
                <a @class(['active' => request()->routeIs('reports.*')]) href="{{ route('reports.index') }}"><i data-lucide="bar-chart-3"></i><span>التقارير</span></a>
                <a @class(['active' => request()->routeIs('notifications.*')]) href="{{ route('notifications.index') }}"><i data-lucide="bell"></i><span>التنبيهات</span></a>
                @role('admin','manager')<a @class(['active' => request()->routeIs('audit.*')]) href="{{ route('audit.index') }}"><i data-lucide="history"></i><span>سجل التدقيق</span></a>@endrole
            </nav>

            <a class="profile-link @if(request()->routeIs('profile.*')) active @endif" href="{{ route('profile.edit') }}">
                <i data-lucide="user-round-cog"></i><span>{{ auth()->user()->name }}</span>
            </a>

            <form method="post" action="{{ route('logout') }}" class="logout-form">
                @csrf
                <button type="submit"><i data-lucide="log-out"></i><span>خروج</span></button>
            </form>
        </aside>

        <main class="main">
            <header class="topbar">
                <button class="icon-button" type="button" data-sidebar-toggle title="القائمة"><i data-lucide="menu"></i></button>
                <div>
                    <h1>{{ $title ?? 'النظام' }}</h1>
                    <p data-live-clock>{{ now()->format('H:i Y-m-d') }}</p>
                </div>
            </header>

            @include('layouts.flash')
            @yield('content')
        </main>
    </div>
@else
    @yield('content')
@endauth
@stack('scripts')
</body>
</html>
