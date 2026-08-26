@extends('layouts.app', ['title' => 'لوحة التحكم'])

@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <script>
        window.tailwind = window.tailwind || {};
        window.tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        arabic: ['Tajawal', 'Segoe UI', 'Tahoma', 'Arial', 'sans-serif'],
                    },
                    boxShadow: {
                        premium: '0 24px 70px rgba(15, 23, 42, .12)',
                        glass: '0 18px 60px rgba(15, 23, 42, .10)',
                    },
                },
            },
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .topbar { display: none !important; }
        .main { padding: 0 !important; background: #f1f5f9 !important; }
        .premium-dashboard { isolation: isolate; }
        .dashboard-pattern { scrollbar-gutter: stable; }
        .dashboard-card {
            background: rgba(255, 255, 255, .9);
            border-color: rgba(226, 232, 240, .9);
        }
        @media (min-width: 1280px) {
            .dashboard-screen {
                height: 100vh;
                min-height: 0;
                overflow: hidden;
            }
            .dashboard-content {
                height: 100vh;
                min-height: 0;
                overflow: hidden;
            }
            .dashboard-main-grid,
            .dashboard-chart-card,
            .dashboard-side {
                min-height: 0;
            }
            .dashboard-chart-card {
                height: 100%;
            }
            .dashboard-watch-list {
                max-height: 100%;
                overflow: hidden;
            }
        }
        .dashboard-pattern {
            background:
                linear-gradient(135deg, rgba(15, 23, 42, .05), transparent 36%, rgba(16, 185, 129, .08)),
                linear-gradient(rgba(15, 23, 42, .035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, .035) 1px, transparent 1px);
            background-size: auto, 38px 38px, 38px 38px;
        }
        .dark .dashboard-pattern {
            background:
                linear-gradient(135deg, rgba(6, 182, 212, .12), transparent 34%, rgba(16, 185, 129, .1)),
                linear-gradient(rgba(255, 255, 255, .045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .045) 1px, transparent 1px);
            background-size: auto, 38px 38px, 38px 38px;
        }
        .apexcharts-tooltip,
        .apexcharts-menu { direction: rtl; font-family: Tajawal, Tahoma, Arial, sans-serif !important; }
        .apexcharts-legend-text,
        .apexcharts-text { font-family: Tajawal, Tahoma, Arial, sans-serif !important; }
    </style>
@endpush

@php
    $formatNumber = fn ($value, int $decimals = 0) => number_format((float) $value, $decimals);
    $emptyUnits = $stats['empty_shelters'] ?? max(0, $stats['shelters'] - $stats['assigned']);
    $criticalTasks = $stats['unassigned'] + $stats['followups'] + $stats['high_security'] + $stats['notifications'];
    // Role profiles live in App\Support\RoleScope so the assistant gates its
    // answers on exactly the same map this dashboard renders from.
    $dashboardProfile = \App\Support\RoleScope::profile(auth()->user());
    $canShowGroup = fn (string $group) => in_array($group, $dashboardProfile['groups'], true);

    $chartPayload = [
        'occupancy' => [
            'labels' => $occupancy->pluck('name')->values(),
            'values' => $occupancy->pluck('total')->values(),
        ],
        'residents' => [
            'labels' => $charts['refugeesByCamp']->pluck('name')->values(),
            'values' => $charts['refugeesByCamp']->pluck('total')->values(),
        ],
        'daily' => [
            'labels' => $dailyActivity->pluck('label')->values(),
            'values' => $dailyActivity->pluck('total')->values(),
        ],
        'aid' => [
            'labels' => $charts['aidByType']->pluck('name')->values(),
            'values' => $charts['aidByType']->pluck('total')->values(),
        ],
        'medical' => [
            'labels' => $charts['medicalByMonth']->pluck('name')->values(),
            'values' => $charts['medicalByMonth']->pluck('total')->values(),
        ],
        'security' => [
            'labels' => $charts['securityBySeverity']->pluck('name')->values(),
            'values' => $charts['securityBySeverity']->pluck('total')->values(),
        ],
        // Donut series. Their slices are mutually exclusive, so each chart's
        // values total a real whole instead of overlapping counts.
        'shelterStates' => $shelterStates,
        'refugeeStates' => $refugeeStates,
        'aidMonth' => $aidMonth['by_type'],
    ];

    $kpis = [
        [
            'label' => 'السكان',
            'value' => $formatNumber($stats['refugees']),
            'hint' => $formatNumber($stats['active_refugees']).' نشط',
            'stat' => 'refugees',
            'hint_key' => 'active_refugees',
            'icon' => 'users-round',
            'tone' => 'from-emerald-500 to-cyan-400',
            'group' => 'registration',
        ],
        [
            'label' => 'الأسر',
            'value' => $formatNumber($stats['households']),
            'hint' => 'ملفات عائلية',
            'stat' => 'households',
            'icon' => 'house',
            'tone' => 'from-teal-500 to-emerald-500',
            'group' => 'registration',
        ],
        [
            'label' => 'الإشغال',
            'value' => $stats['occupancy_percentage'].'%',
            'hint' => $formatNumber($stats['assigned']).' مخصص',
            'stat' => 'occupancy_percentage',
            'suffix' => '%',
            'hint_key' => 'assigned',
            'icon' => 'gauge',
            'tone' => 'from-cyan-500 to-blue-500',
            'group' => 'housing',
        ],
        [
            'label' => 'فارغة',
            'value' => $formatNumber($emptyUnits),
            'hint' => 'وحدات متاحة',
            'stat' => 'empty_shelters',
            'icon' => 'home',
            'tone' => 'from-slate-500 to-slate-700',
            'group' => 'housing',
        ],
        [
            'label' => 'بدون سكن',
            'value' => $formatNumber($stats['unassigned']),
            'hint' => 'تحتاج تخصيص',
            'stat' => 'unassigned',
            'icon' => 'bed',
            'tone' => 'from-amber-400 to-orange-500',
            'group' => 'housing',
        ],
        [
            'label' => 'المساعدات',
            'value' => $formatNumber($stats['aid']),
            'hint' => 'عمليات توزيع',
            'stat' => 'aid',
            'icon' => 'package-check',
            'tone' => 'from-amber-400 to-yellow-500',
            'group' => 'aid',
        ],
        [
            'label' => 'طبية',
            'value' => $formatNumber($stats['followups']),
            'hint' => 'متابعة مفتوحة',
            'stat' => 'followups',
            'icon' => 'stethoscope',
            'tone' => 'from-teal-500 to-emerald-500',
            'group' => 'medical',
        ],
        [
            'label' => 'أمنية',
            'value' => $formatNumber($stats['high_security']),
            'hint' => 'عالية/حرجة',
            'stat' => 'high_security',
            'icon' => 'shield-alert',
            'tone' => 'from-rose-500 to-orange-500',
            'group' => 'security',
        ],
    ];
    $kpis = array_values(array_filter($kpis, fn ($card) => $canShowGroup($card['group'])));

    $tabs = [
        ['id' => 'occupancy', 'label' => 'الإشغال', 'icon' => 'activity'],
        ['id' => 'shelterStates', 'label' => 'حالة الوحدات', 'icon' => 'pie-chart'],
        ['id' => 'residents', 'label' => 'السكان', 'icon' => 'users-round'],
        ['id' => 'refugeeStates', 'label' => 'حالات السكان', 'icon' => 'chart-pie'],
        ['id' => 'daily', 'label' => 'النشاط', 'icon' => 'bar-chart-3'],
        ['id' => 'aid', 'label' => 'المساعدات', 'icon' => 'package-check'],
        ['id' => 'aidMonth', 'label' => 'مساعدات الشهر', 'icon' => 'calendar-range'],
        ['id' => 'medical', 'label' => 'الطبي', 'icon' => 'heart-pulse'],
        ['id' => 'security', 'label' => 'الأمن', 'icon' => 'shield-alert'],
    ];
    $tabGroups = [
        'occupancy' => 'housing',
        'shelterStates' => 'housing',
        'residents' => 'registration',
        'refugeeStates' => 'registration',
        'daily' => 'management',
        'aid' => 'aid',
        'aidMonth' => 'aid',
        'medical' => 'medical',
        'security' => 'security',
    ];
    $tabs = array_values(array_filter($tabs, fn ($tab) => $canShowGroup($tabGroups[$tab['id']] ?? 'management')));
    $defaultChart = $tabs[0]['id'] ?? 'daily';

    $quickActions = [
        ['label' => 'إضافة لاجئ', 'icon' => 'user-plus', 'href' => route('refugees.create'), 'roles' => ['admin', 'registration_officer']],
        ['label' => 'تخصيص سكن', 'icon' => 'bed', 'href' => route('housing.unassigned'), 'roles' => ['admin', 'housing_officer']],
        ['label' => 'سجل طبي', 'icon' => 'clipboard-plus', 'href' => route('medical.records.create'), 'roles' => ['admin', 'medical_officer']],
        ['label' => 'تقرير', 'icon' => 'file-bar-chart', 'href' => route('reports.index'), 'roles' => ['admin', 'manager', 'registration_officer', 'housing_officer', 'aid_officer', 'medical_officer', 'security_officer']],
    ];

    $watchItems = [
        ['label' => 'بدون سكن', 'value' => $stats['unassigned'], 'stat' => 'unassigned', 'href' => route('housing.unassigned'), 'icon' => 'bed', 'tone' => 'amber', 'group' => 'housing', 'roles' => ['admin', 'housing_officer']],
        ['label' => 'متابعات طبية', 'value' => $stats['followups'], 'stat' => 'followups', 'href' => route('medical.records'), 'icon' => 'stethoscope', 'tone' => 'emerald', 'group' => 'medical', 'roles' => ['admin', 'medical_officer']],
        ['label' => 'تنبيهات أمنية', 'value' => $stats['high_security'], 'stat' => 'high_security', 'href' => route('security.reports'), 'icon' => 'shield-alert', 'tone' => 'rose', 'group' => 'security', 'roles' => ['admin', 'security_officer']],
        ['label' => 'تنبيهات غير مقروءة', 'value' => $stats['notifications'], 'stat' => 'notifications', 'href' => route('notifications.index'), 'icon' => 'bell-ring', 'tone' => 'cyan', 'group' => 'management', 'roles' => ['admin', 'manager', 'registration_officer', 'housing_officer', 'aid_officer', 'medical_officer', 'security_officer']],
    ];
    $watchItems = array_values(array_filter($watchItems, fn ($item) => $canShowGroup($item['group']) && auth()->user()->hasAnyRole($item['roles'])));
@endphp

@section('content')
    <div
        x-data="focusedCampDashboard(@js($chartPayload), @js($defaultChart), { liveUrl: @js(route('dashboard.live')), refreshMs: 30000 })"
        class="premium-dashboard font-arabic text-slate-950"
        dir="rtl"
    >
        <div class="dashboard-pattern dashboard-screen relative min-h-screen overflow-y-auto bg-slate-100 transition-colors duration-300 dark:bg-slate-950 dark:text-white">
            <div class="pointer-events-none absolute -right-32 -top-32 h-80 w-80 rounded-full bg-emerald-400/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -left-32 bottom-0 h-96 w-96 rounded-full bg-cyan-400/20 blur-3xl"></div>

            <div class="dashboard-content relative flex min-h-screen w-full flex-col gap-2.5 p-2.5 sm:p-3">
                <header class="dashboard-card relative z-[120] shrink-0 rounded-2xl border px-2.5 py-2 shadow-premium backdrop-blur-2xl">
                    <div class="flex flex-col gap-1.5 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex items-center gap-2.5">
                            <button type="button" data-sidebar-toggle class="grid h-11 w-11 place-items-center rounded-2xl border border-slate-200 bg-white text-slate-700 transition hover:border-emerald-300 hover:text-emerald-600 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 lg:hidden" title="القائمة">
                                <i data-lucide="menu" class="h-5 w-5"></i>
                            </button>
                            <span class="hidden h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-emerald-500 to-cyan-500 text-white shadow-lg shadow-emerald-500/20 sm:grid">
                                <i data-lucide="tent" class="h-5 w-5"></i>
                            </span>
                            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                                <div class="min-w-0">
                                    <h1 class="text-xl font-black leading-tight tracking-normal text-slate-950 dark:text-white">{{ $dashboardProfile['title'] }}</h1>
                                    <p class="text-[11px] font-bold leading-tight text-slate-500">{{ $dashboardProfile['subtitle'] }}</p>
                                </div>
                                <div class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-extrabold text-emerald-700 dark:text-emerald-300">
                                    <i data-lucide="radio-tower" class="h-3 w-3"></i>
                                    متابعة مباشرة
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-black text-slate-700 shadow-sm" data-live-clock>
                                {{ now()->format('H:i Y-m-d') }}
                            </div>
                            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-1.5 text-sm font-black text-emerald-700" data-live-health>
                                استقرار {{ $healthScore }}%
                            </div>

                            <div class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                                <button type="button" data-dashboard-notification-trigger @click="open = true" class="relative grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-cyan-300 hover:text-cyan-600">
                                    <i data-lucide="bell" class="h-5 w-5"></i>
                                    <span data-live-notification-dot class="absolute right-2 top-2 h-2.5 w-2.5 rounded-full bg-rose-500 ring-4 ring-white {{ $stats['notifications'] > 0 ? '' : 'hidden' }}"></span>
                                </button>
                                <div x-cloak x-show="open" x-transition data-dashboard-notification-flyout class="absolute left-0 z-[200] mt-3 w-80 max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white p-3 shadow-premium">
                                    <div class="mb-2 flex items-center justify-between">
                                        <strong class="text-sm text-slate-950">التنبيهات</strong>
                                        <a href="{{ route('notifications.index') }}" class="text-xs font-black text-emerald-600">عرض الكل</a>
                                    </div>
                                    <div class="grid gap-2" data-live-notifications>
                                        @forelse ($recentNotifications->take(3) as $notification)
                                            <a href="{{ route('notifications.index') }}" class="rounded-2xl border border-slate-100 bg-slate-50 p-3 text-sm font-bold text-slate-700 transition hover:border-emerald-200 hover:bg-white">
                                                {{ $notification->title }}
                                                <span class="mt-1 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                                            </a>
                                        @empty
                                            <div class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-500">لا توجد تنبيهات حالياً</div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            <div class="relative" x-data="{ open: false }">
                                <button type="button" @click="open = !open" class="flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 shadow-sm transition hover:border-emerald-300">
                                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-gradient-to-br from-emerald-500 to-cyan-500 text-xs font-black text-white">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                                    <span class="hidden text-sm font-black text-slate-800 sm:block">{{ auth()->user()->name }}</span>
                                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400"></i>
                                </button>
                                <div x-cloak x-show="open" @click.outside="open = false" x-transition class="absolute left-0 z-[200] mt-3 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-premium">
                                    <a href="{{ route('reports.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50"><i data-lucide="bar-chart-3" class="h-4 w-4"></i> التقارير</a>
                                    <form method="post" action="{{ route('logout') }}" class="mt-1 border-t border-slate-100 pt-1">
                                        @csrf
                                        <button type="submit" class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-right text-sm font-bold text-rose-600 transition hover:bg-rose-50"><i data-lucide="log-out" class="h-4 w-4"></i> تسجيل الخروج</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <section class="grid shrink-0 grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8">
                    @foreach ($kpis as $card)
                        <article class="dashboard-card group min-h-[92px] rounded-2xl border p-3 shadow-glass backdrop-blur-xl transition hover:-translate-y-0.5 hover:shadow-premium dark:border-white/10 dark:bg-slate-900/80">
                            <div class="flex items-start justify-between">
                                <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br {{ $card['tone'] }} text-white shadow-lg shadow-slate-950/10">
                                    <i data-lucide="{{ $card['icon'] }}" class="h-4 w-4"></i>
                                </span>
                                <span class="text-xl font-black text-slate-950 dark:text-white" data-live-stat="{{ $card['stat'] }}" data-live-suffix="{{ $card['suffix'] ?? '' }}">{{ $card['value'] }}</span>
                            </div>
                            <div class="mt-2">
                                <h3 class="text-[13px] font-black text-slate-800 dark:text-slate-100">{{ $card['label'] }}</h3>
                                <p class="mt-0.5 text-[11px] font-bold text-slate-500 dark:text-slate-400" data-live-hint="{{ $card['hint_key'] ?? '' }}">{{ $card['hint'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </section>

                <main class="dashboard-main-grid grid flex-1 gap-2.5 xl:grid-cols-[minmax(0,1fr)_320px]">
                    <section class="dashboard-chart-card dashboard-card flex min-h-[390px] flex-col rounded-2xl border p-3 shadow-premium backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/80">
                        <div class="flex shrink-0 flex-col gap-2 border-b border-slate-200 pb-2 dark:border-white/10 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h2 class="text-lg font-black text-slate-950 dark:text-white">المؤشر التشغيلي</h2>
                                <p class="mt-0.5 text-xs font-bold text-slate-500 dark:text-slate-400">مخطط واحد يختصر الحالة بدون ازدحام.</p>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($tabs as $tab)
                                    <button type="button" @click="setChart('{{ $tab['id'] }}')" :class="activeChart === '{{ $tab['id'] }}' ? 'bg-slate-900 text-white shadow-lg shadow-slate-950/10 dark:bg-white dark:text-slate-950' : 'bg-white text-slate-600 hover:text-emerald-600 dark:bg-slate-800 dark:text-slate-300'" class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-200 px-2.5 py-1.5 text-xs font-black transition dark:border-white/10">
                                        <i data-lucide="{{ $tab['icon'] }}" class="h-4 w-4"></i>
                                        {{ $tab['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div class="relative min-h-0 flex-1 pt-2">
                            <div x-show="loading" class="absolute inset-x-0 top-3 z-10 h-[calc(100%-0.75rem)] animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800"></div>
                            <div id="mainDashboardChart" class="h-[300px] xl:h-full"></div>
                        </div>
                    </section>

                    <aside class="dashboard-side grid content-start gap-2.5 xl:grid-rows-[auto_minmax(0,1fr)]">
                        @if ($canShowGroup('aid'))
                            @php
                                $aidChange = $aidMonth['change_percentage'];
                                $aidTiles = [
                                    ['key' => 'operations', 'label' => 'عملية توزيع', 'value' => $formatNumber($aidMonth['operations']), 'icon' => 'package-check'],
                                    ['key' => 'beneficiaries', 'label' => 'مستفيد', 'value' => $formatNumber($aidMonth['beneficiaries']), 'icon' => 'users-round'],
                                    ['key' => 'quantity', 'label' => 'إجمالي الكميات', 'value' => $formatNumber($aidMonth['quantity']), 'icon' => 'boxes'],
                                ];
                            @endphp
                            <section class="dashboard-card rounded-2xl border p-3 shadow-glass backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/80">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <h2 class="text-base font-black text-slate-950 dark:text-white">مساعدات هذا الشهر</h2>
                                        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">منذ {{ now()->startOfMonth()->format('Y-m-d') }}</p>
                                    </div>
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-2xl bg-amber-500/10 text-amber-600">
                                        <i data-lucide="hand-heart" class="h-5 w-5"></i>
                                    </span>
                                </div>

                                <div class="grid grid-cols-3 gap-1.5">
                                    @foreach ($aidTiles as $tile)
                                        <div class="rounded-2xl border border-slate-200 bg-white p-2 text-center dark:border-white/10 dark:bg-slate-800">
                                            <i data-lucide="{{ $tile['icon'] }}" class="mx-auto h-4 w-4 text-amber-600"></i>
                                            <span class="mt-1 block text-lg font-black leading-none text-slate-950 dark:text-white"
                                                  data-aid-month="{{ $tile['key'] }}">{{ $tile['value'] }}</span>
                                            <span class="mt-0.5 block text-[10px] font-bold leading-tight text-slate-500 dark:text-slate-400">{{ $tile['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-2 flex items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-2.5 py-2 dark:border-white/10 dark:bg-slate-800/60">
                                    <span class="min-w-0 truncate text-[11px] font-bold text-slate-500 dark:text-slate-400">
                                        الأكثر توزيعًا: <strong class="text-slate-800 dark:text-slate-100" data-aid-month="top_type">{{ $aidMonth['top_type'] }}</strong>
                                    </span>
                                    @if ($aidChange === null)
                                        {{-- No comparable window last month, so no percentage is claimed. --}}
                                        <span class="shrink-0 text-[11px] font-bold text-slate-400">لا مقارنة</span>
                                    @else
                                        <span @class([
                                            'inline-flex shrink-0 items-center gap-1 rounded-xl px-2 py-0.5 text-[11px] font-black',
                                            'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' => $aidChange > 0,
                                            'bg-rose-500/10 text-rose-700 dark:text-rose-400' => $aidChange < 0,
                                            'bg-slate-500/10 text-slate-600 dark:text-slate-300' => $aidChange == 0,
                                        ])>
                                            <i data-lucide="{{ $aidChange > 0 ? 'trending-up' : ($aidChange < 0 ? 'trending-down' : 'minus') }}" class="h-3.5 w-3.5"></i>
                                            {{ $aidChange > 0 ? '+' : '' }}{{ $formatNumber(abs($aidChange)) }}%
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1.5 text-[10px] font-bold leading-tight text-slate-400">
                                    المقارنة مع نفس عدد الأيام من الشهر الماضي ({{ $formatNumber($aidMonth['previous_operations']) }} عملية).
                                </p>
                            </section>
                        @endif

                        <section class="dashboard-card rounded-2xl border p-3 shadow-glass backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/80">
                            <div class="mb-2 flex items-center justify-between">
                                <div>
                                    <h2 class="text-base font-black text-slate-950 dark:text-white">إجراءات سريعة</h2>
                                    <p class="text-xs font-bold text-slate-500 dark:text-slate-400">الأوامر الضرورية فقط.</p>
                                </div>
                                <span class="grid h-9 w-9 place-items-center rounded-2xl bg-emerald-500/10 text-emerald-600">
                                    <i data-lucide="zap" class="h-5 w-5"></i>
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                @foreach ($quickActions as $action)
                                    @if (auth()->user()->hasAnyRole($action['roles']))
                                        <a href="{{ $action['href'] }}" class="group flex min-h-[58px] items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2.5 text-sm font-black text-slate-800 transition hover:border-emerald-300 hover:bg-emerald-50 dark:border-white/10 dark:bg-slate-800 dark:text-white dark:hover:bg-emerald-500/10">
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-slate-100 text-emerald-600 transition group-hover:bg-emerald-600 group-hover:text-white dark:bg-slate-900">
                                                <i data-lucide="{{ $action['icon'] }}" class="h-4 w-4"></i>
                                            </span>
                                            {{ $action['label'] }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </section>

                        <section class="dashboard-card min-h-0 rounded-2xl border p-2.5 shadow-glass backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/80">
                            <div class="mb-1.5 flex items-center justify-between gap-2">
                                <div>
                                    <h2 class="text-sm font-black leading-tight text-slate-950 dark:text-white">تحتاج متابعة</h2>
                                    <p class="text-[11px] font-bold leading-tight text-slate-500 dark:text-slate-400"><span data-live-critical>{{ $formatNumber($criticalTasks) }}</span> بند مفتوح حالياً</p>
                                </div>
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-amber-500/10 text-amber-600">
                                    <i data-lucide="list-checks" class="h-4 w-4"></i>
                                </span>
                            </div>
                            <div class="dashboard-watch-list grid gap-1.5">
                                @foreach ($watchItems as $item)
                                    <a href="{{ $item['href'] }}" class="group flex min-h-[54px] items-center justify-between rounded-2xl border border-slate-200 bg-white px-2.5 py-2 transition hover:-translate-y-0.5 hover:border-emerald-300 dark:border-white/10 dark:bg-slate-800">
                                        <span class="flex min-w-0 items-center gap-2">
                                            <span @class([
                                                'grid h-8 w-8 shrink-0 place-items-center rounded-xl',
                                                'bg-amber-500/10 text-amber-600' => $item['tone'] === 'amber',
                                                'bg-emerald-500/10 text-emerald-600' => $item['tone'] === 'emerald',
                                                'bg-rose-500/10 text-rose-600' => $item['tone'] === 'rose',
                                                'bg-cyan-500/10 text-cyan-600' => $item['tone'] === 'cyan',
                                            ])>
                                                <i data-lucide="{{ $item['icon'] }}" class="h-4 w-4"></i>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-[12px] font-black text-slate-800 dark:text-white">{{ $item['label'] }}</span>
                                                <span class="block text-[11px] font-bold text-slate-500 dark:text-slate-400">فتح القائمة</span>
                                            </span>
                                        </span>
                                        <span class="shrink-0 text-lg font-black text-slate-950 dark:text-white" data-live-stat="{{ $item['stat'] }}">{{ $formatNumber($item['value']) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    </aside>
                </main>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('focusedCampDashboard', (payload, defaultChart = 'occupancy', liveOptions = {}) => ({
                    loading: true,
                    activeChart: defaultChart,
                    chart: null,
                    payload,
                    liveUrl: liveOptions.liveUrl || null,
                    refreshMs: Number(liveOptions.refreshMs || 30000),
                    refreshTimer: null,
                    visibilityHandler: null,

                    init() {
                        setTimeout(() => { this.loading = false; }, 500);
                        this.$nextTick(() => {
                            this.renderChart();
                            this.refreshIcons();
                        });
                        this.startLiveRefresh();
                        localStorage.removeItem('campDashboardTheme');
                    },

                    refreshIcons() {
                        if (window.lucide) {
                            window.lucide.createIcons();
                        }
                    },

                    startLiveRefresh() {
                        if (!this.liveUrl) return;

                        this.refreshDashboard();
                        this.refreshTimer = window.setInterval(() => this.refreshDashboard(), this.refreshMs);
                        this.visibilityHandler = () => {
                            if (!document.hidden) {
                                this.refreshDashboard();
                            }
                        };
                        document.addEventListener('visibilitychange', this.visibilityHandler);
                    },

                    async refreshDashboard() {
                        if (!this.liveUrl) return;

                        try {
                            const response = await fetch(this.liveUrl, {
                                headers: { Accept: 'application/json' },
                                cache: 'no-store',
                            });

                            if (!response.ok) return;

                            const data = await response.json();
                            if (data.charts) {
                                this.payload = data.charts;
                                if (this.chart) {
                                    // Same type throughout a refresh, so an update is safe here.
                                    this.chart.updateOptions(this.options(), false, true);
                                }
                            }

                            if (data.aidMonth) {
                                this.updateAidMonth(data.aidMonth);
                            }

                            this.updateLiveStats(data.stats || {}, data.healthScore, data.criticalTasks);
                            this.updateLiveNotifications(data.notifications || [], data.stats?.notifications || 0);
                            this.refreshIcons();
                        } catch (error) {
                            console.warn('Dashboard live refresh failed', error);
                        }
                    },

                    updateAidMonth(aid) {
                        document.querySelectorAll('[data-aid-month]').forEach((element) => {
                            const key = element.dataset.aidMonth;
                            if (aid[key] === undefined || aid[key] === null) return;
                            element.textContent = typeof aid[key] === 'number'
                                ? this.formatValue(aid[key])
                                : String(aid[key]);
                        });
                    },

                    updateLiveStats(stats, healthScore, criticalTasks) {
                        document.querySelectorAll('[data-live-stat]').forEach((element) => {
                            const key = element.dataset.liveStat;
                            if (!key || stats[key] === undefined) return;
                            element.textContent = this.formatValue(stats[key], element.dataset.liveSuffix || '');
                        });

                        document.querySelectorAll('[data-live-hint]').forEach((element) => {
                            const hint = this.hintFor(element.dataset.liveHint, stats);
                            if (hint) {
                                element.textContent = hint;
                            }
                        });

                        const health = document.querySelector('[data-live-health]');
                        if (health && healthScore !== undefined) {
                            health.textContent = `استقرار ${this.formatValue(healthScore, '%')}`;
                        }

                        const critical = document.querySelector('[data-live-critical]');
                        if (critical && criticalTasks !== undefined) {
                            critical.textContent = this.formatValue(criticalTasks);
                        }
                    },

                    updateLiveNotifications(notifications, unreadCount) {
                        const dot = document.querySelector('[data-live-notification-dot]');
                        if (dot) {
                            dot.classList.toggle('hidden', Number(unreadCount || 0) <= 0);
                        }

                        const list = document.querySelector('[data-live-notifications]');
                        if (!list) return;

                        if (!notifications.length) {
                            list.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-500">لا توجد تنبيهات حالياً</div>';
                            return;
                        }

                        list.innerHTML = notifications.map((notification) => `
                            <a href="${this.escapeHtml(notification.url || '#')}" class="rounded-2xl border border-slate-100 bg-slate-50 p-3 text-sm font-bold text-slate-700 transition hover:border-emerald-200 hover:bg-white">
                                ${this.escapeHtml(notification.title || 'تنبيه')}
                                <span class="mt-1 block text-xs text-slate-400">${this.escapeHtml(notification.time || '')}</span>
                            </a>
                        `).join('');
                    },

                    formatValue(value, suffix = '') {
                        const numeric = Number(value || 0);
                        const decimals = Number.isInteger(numeric) ? 0 : 1;
                        return `${numeric.toLocaleString('ar', { maximumFractionDigits: decimals })}${suffix}`;
                    },

                    hintFor(key, stats) {
                        const hints = {
                            active_refugees: `${this.formatValue(stats.active_refugees)} نشط`,
                            assigned: `${this.formatValue(stats.assigned)} مخصص`,
                        };

                        return key ? hints[key] : '';
                    },

                    escapeHtml(value) {
                        return String(value)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                    },

                    safeData(key) {
                        const source = this.payload[key] || {};
                        const labels = Array.isArray(source.labels) && source.labels.length ? source.labels : ['لا توجد بيانات'];
                        const values = Array.isArray(source.values) && source.values.length ? source.values.map((value) => Number(value) || 0) : [0];
                        return { labels, values };
                    },

                    meta() {
                        return {
                            occupancy: { name: 'نسبة الإشغال', type: 'bar', color: '#5aa99d', stroke: '#3f827b' },
                            residents: { name: 'السكان', type: 'bar', color: '#6f9fbd', stroke: '#507c99' },
                            daily: { name: 'النشاط اليومي', type: 'area', color: '#7f95b8', stroke: '#637896' },
                            aid: { name: 'المساعدات', type: 'bar', color: '#c7a869', stroke: '#9b7f42' },
                            medical: { name: 'السجلات الطبية', type: 'area', color: '#73ad96', stroke: '#53856f' },
                            security: { name: 'التقارير الأمنية', type: 'bar', color: '#bf7a76', stroke: '#965b58' },
                            // Donuts carry one hue per slice. The three-hue set was
                            // checked for colour-vision separation rather than picked
                            // by eye, and every slice is direct-labelled as well, so
                            // identity never rests on colour alone.
                            shelterStates: {
                                name: 'الوحدات السكنية',
                                type: 'donut',
                                palette: ['#c8503a', '#0f8a5f', '#2f6fd0'],
                                total: 'وحدة',
                            },
                            refugeeStates: {
                                name: 'السكان',
                                type: 'donut',
                                palette: ['#0f8a5f', '#2f6fd0', '#b8791a'],
                                total: 'شخص',
                            },
                            aidMonth: { name: 'مساعدات الشهر', type: 'bar', color: '#b8791a', stroke: '#8a5b12' },
                        }[this.activeChart];
                    },

                    options() {
                        const data = this.safeData(this.activeChart);
                        const meta = this.meta();

                        if (meta.type === 'donut') {
                            return this.donutOptions(data, meta);
                        }

                        return {
                            chart: {
                                type: meta.type,
                                height: '100%',
                                fontFamily: 'Tajawal, Tahoma, Arial, sans-serif',
                                toolbar: { show: false },
                                foreColor: '#475569',
                                animations: { enabled: true, easing: 'easeinout', speed: 650 },
                            },
                            series: [{ name: meta.name, data: data.values }],
                            colors: [meta.color],
                            dataLabels: {
                                enabled: meta.type === 'bar',
                                formatter: (value) => Number(value).toLocaleString('ar'),
                                style: { fontSize: '11px', fontWeight: 900, colors: ['#334155'] },
                                offsetY: -18,
                                background: { enabled: false },
                            },
                            stroke: meta.type === 'bar'
                                ? { width: 1, colors: [meta.stroke || meta.color] }
                                : { curve: 'smooth', width: 3, colors: [meta.stroke || meta.color] },
                            fill: meta.type === 'area'
                                ? { type: 'gradient', gradient: { opacityFrom: .36, opacityTo: .1 } }
                                : { type: 'solid', opacity: .82 },
                            grid: {
                                borderColor: '#e5edf3',
                                strokeDashArray: 4,
                                padding: { left: 12, right: 12, top: 18, bottom: 4 },
                            },
                            plotOptions: {
                                bar: {
                                    borderRadius: 9,
                                    columnWidth: data.labels.length > 8 ? '52%' : '44%',
                                    distributed: false,
                                    dataLabels: { position: 'top' },
                                },
                            },
                            xaxis: {
                                categories: data.labels,
                                tickAmount: Math.min(data.labels.length, 8),
                                labels: {
                                    rotate: 0,
                                    trim: true,
                                    hideOverlappingLabels: true,
                                    style: { fontSize: '12px', fontWeight: 800, colors: '#475569' },
                                    formatter: (value) => String(value || '').replace(/^مخيم\s+/, '').slice(0, 12),
                                },
                                axisBorder: { show: false },
                                axisTicks: { show: false },
                            },
                            yaxis: {
                                max: this.activeChart === 'occupancy' ? 100 : undefined,
                                min: 0,
                                labels: {
                                    style: { colors: '#64748b', fontWeight: 800 },
                                    formatter: (value) => Number(value).toLocaleString('ar'),
                                },
                            },
                            states: {
                                hover: { filter: { type: 'darken', value: .08 } },
                                active: { filter: { type: 'none' } },
                            },
                            tooltip: { theme: 'light' },
                            noData: {
                                text: 'لا توجد بيانات كافية',
                                style: {
                                    color: '#64748b',
                                    fontSize: '14px',
                                    fontFamily: 'Tajawal, Tahoma, Arial, sans-serif',
                                },
                            },
                        };
                    },

                    /**
                     * A donut needs a flat series and top-level labels, unlike the
                     * cartesian charts which take categories on the x axis.
                     */
                    donutOptions(data, meta) {
                        const total = data.values.reduce((sum, value) => sum + value, 0);

                        return {
                            chart: {
                                type: 'donut',
                                height: '100%',
                                fontFamily: 'Tajawal, Tahoma, Arial, sans-serif',
                                toolbar: { show: false },
                                foreColor: '#475569',
                                animations: { enabled: true, easing: 'easeinout', speed: 650 },
                            },
                            series: data.values,
                            labels: data.labels,
                            colors: meta.palette,
                            // A 2px gap in the surface colour keeps adjacent slices
                            // readable without a heavy outline.
                            stroke: { width: 2, colors: ['#ffffff'] },
                            legend: {
                                position: 'bottom',
                                horizontalAlign: 'center',
                                fontWeight: 800,
                                fontSize: '12px',
                                markers: { radius: 4 },
                                itemMargin: { horizontal: 8, vertical: 4 },
                            },
                            dataLabels: {
                                enabled: true,
                                formatter: (percent) => `${Number(percent).toFixed(0)}%`,
                                style: { fontSize: '12px', fontWeight: 900, colors: ['#ffffff'] },
                                dropShadow: { enabled: false },
                            },
                            plotOptions: {
                                pie: {
                                    donut: {
                                        size: '64%',
                                        labels: {
                                            show: true,
                                            name: { fontSize: '13px', fontWeight: 800, color: '#475569' },
                                            value: {
                                                fontSize: '22px',
                                                fontWeight: 900,
                                                color: '#0f172a',
                                                formatter: (value) => Number(value).toLocaleString('ar'),
                                            },
                                            total: {
                                                show: true,
                                                showAlways: true,
                                                label: `الإجمالي (${meta.total})`,
                                                fontSize: '12px',
                                                fontWeight: 800,
                                                color: '#64748b',
                                                formatter: () => total.toLocaleString('ar'),
                                            },
                                        },
                                    },
                                },
                            },
                            tooltip: {
                                theme: 'light',
                                y: {
                                    formatter: (value) => {
                                        const share = total > 0 ? Math.round((value / total) * 100) : 0;
                                        return `${Number(value).toLocaleString('ar')} (${share}%)`;
                                    },
                                },
                            },
                            noData: {
                                text: 'لا توجد بيانات كافية',
                                style: { color: '#64748b', fontSize: '14px', fontFamily: 'Tajawal, Tahoma, Arial, sans-serif' },
                            },
                        };
                    },

                    setChart(id) {
                        const previousType = this.meta()?.type;
                        this.activeChart = id;
                        const nextType = this.meta()?.type;

                        // ApexCharts cannot switch between a cartesian chart and a
                        // donut through updateOptions — the old axes survive and the
                        // new series is misread. Only a same-type switch is an update.
                        if (this.chart && previousType === nextType) {
                            this.chart.updateOptions(this.options(), true, true);
                        } else {
                            this.rebuildChart();
                        }

                        this.refreshIcons();
                    },

                    rebuildChart() {
                        if (this.chart) {
                            this.chart.destroy();
                            this.chart = null;
                        }
                        this.renderChart();
                    },

                    renderChart() {
                        const element = document.querySelector('#mainDashboardChart');
                        if (!element || !window.ApexCharts) return;
                        this.chart = new ApexCharts(element, this.options());
                        this.chart.render();
                    },

                    destroyChart() {
                        if (this.refreshTimer) {
                            window.clearInterval(this.refreshTimer);
                            this.refreshTimer = null;
                        }
                        if (this.visibilityHandler) {
                            document.removeEventListener('visibilitychange', this.visibilityHandler);
                            this.visibilityHandler = null;
                        }
                        if (this.chart) {
                            this.chart.destroy();
                            this.chart = null;
                        }
                    },
                }));
            });
        </script>
    @endpush
@endsection
