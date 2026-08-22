<?php

namespace App\Http\Controllers;

use App\Models\AidDistribution;
use App\Models\Camp;
use App\Models\EntryExitLog;
use App\Models\Household;
use App\Models\MedicalRecord;
use App\Models\Notification;
use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use App\Models\SecurityReport;
use App\Models\Shelter;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.index', $this->dashboardData());
    }

    public function live(): JsonResponse
    {
        $data = $this->dashboardData();
        $stats = $data['stats'];
        $criticalTasks = $stats['unassigned'] + $stats['followups'] + $stats['high_security'] + $stats['notifications'];

        return response()->json([
            'stats' => $stats,
            'healthScore' => $data['healthScore'],
            'criticalTasks' => $criticalTasks,
            'charts' => $this->chartPayload($data['charts'], $data['occupancy'], $data['dailyActivity']),
            'notifications' => $data['recentNotifications']
                ->take(3)
                ->map(fn (Notification $notification): array => [
                    'title' => $notification->title,
                    'time' => $notification->created_at?->diffForHumans() ?? '',
                    'url' => route('notifications.index'),
                ])
                ->values(),
        ]);
    }

    private function dashboardData(): array
    {
        $user = auth()->user();
        $shelterLoad = Shelter::query()
            ->select(['id', 'camp_id', 'code', 'capacity', 'status'])
            ->withCount(['refugees as occupied' => fn ($query) => $query->where('status', 'active')])
            ->get();
        $shelterCapacity = (int) $shelterLoad->sum('capacity');
        $shelterOccupied = (int) $shelterLoad->sum('occupied');
        $occupancyPercentage = $shelterCapacity > 0 ? round(($shelterOccupied / $shelterCapacity) * 100, 1) : 0;

        $unreadNotifications = $this->visibleUnreadNotificationCount($user);
        $recentNotifications = Notification::visibleFor($user)
            ->where('status', '!=', 'resolved')
            ->latest()
            ->limit(80)
            ->get()
            ->unique('dedupe_key')
            ->take(6)
            ->values();

        $stats = [
            'refugees' => Refugee::count(),
            'active_refugees' => Refugee::where('status', 'active')->count(),
            'households' => Household::count(),
            'camps' => Camp::count(),
            'shelters' => Shelter::count(),
            'empty_shelters' => $shelterLoad->where('occupied', 0)->count(),
            'unassigned' => Refugee::where('housing_status', 'unassigned')->count(),
            'assigned' => Refugee::where('housing_status', 'assigned')->count(),
            'outside' => Refugee::where('presence_status', 'outside')->count(),
            'aid' => AidDistribution::count(),
            'medical' => MedicalRecord::count(),
            'followups' => MedicalRecord::where('needs_follow_up', true)->count(),
            'movements' => EntryExitLog::count(),
            'security' => SecurityReport::count(),
            'high_security' => SecurityReport::whereIn('severity', ['high', 'critical'])->count(),
            'notifications' => $unreadNotifications,
            'occupancy_percentage' => $occupancyPercentage,
        ];

        $campLoad = Camp::query()
            ->leftJoin('refugees', function ($join): void {
                $join->on('camps.id', '=', 'refugees.current_camp_id')
                    ->where('refugees.status', '=', 'active');
            })
            ->select('camps.id', 'camps.name', 'camps.capacity', DB::raw('count(refugees.id) as total'))
            ->groupBy('camps.id', 'camps.name', 'camps.capacity')
            ->orderBy('camps.name')
            ->get();

        $charts = [
            'refugeesByCamp' => $campLoad,
            'aidByType' => AidDistribution::query()
                ->join('aid_types', 'aid_types.id', '=', 'aid_distributions.aid_type_id')
                ->select('aid_types.name', DB::raw('count(*) as total'))
                ->groupBy('aid_types.name')
                ->get(),
            'securityBySeverity' => SecurityReport::query()
                ->select('severity as name', DB::raw('count(*) as total'))
                ->groupBy('severity')
                ->get(),
            'medicalByMonth' => MedicalRecord::query()
                ->select(DB::raw("DATE_FORMAT(record_date, '%Y-%m') as name"), DB::raw('count(*) as total'))
                ->where('record_date', '>=', now()->subMonths(6)->startOfMonth())
                ->groupBy('name')
                ->orderBy('name')
                ->get(),
        ];

        $campNames = Camp::whereIn('id', $shelterLoad->pluck('camp_id')->unique())->pluck('name', 'id');
        $occupancy = $shelterLoad
            ->groupBy('camp_id')
            ->map(function (Collection $campShelters, int $campId) use ($campNames): array {
                $capacity = (int) $campShelters->sum('capacity');
                $occupied = (int) $campShelters->sum('occupied');

                return [
                    'name' => $campNames[$campId] ?? 'مخيم',
                    'total' => $capacity > 0 ? min(100, round(($occupied / $capacity) * 100, 1)) : 0,
                ];
            })
            ->values();

        $healthScore = max(0, 100 - min(85, ($stats['unassigned'] * 7) + ($stats['followups'] * 5) + ($stats['high_security'] * 12)));
        $dailyActivity = collect(range(6, 0))->map(function (int $daysAgo): array {
            $date = now()->subDays($daysAgo)->toDateString();

            return [
                'label' => Carbon::parse($date)->format('m/d'),
                'total' => Refugee::whereDate('created_at', $date)->count()
                    + AidDistribution::whereDate('created_at', $date)->count()
                    + MedicalRecord::whereDate('created_at', $date)->count()
                    + EntryExitLog::whereDate('created_at', $date)->count(),
            ];
        });

        return compact(
            'stats',
            'charts',
            'occupancy',
            'recentNotifications',
            'healthScore',
            'dailyActivity'
        );
    }

    private function chartPayload(array $charts, Collection $occupancy, Collection $dailyActivity): array
    {
        return [
            'occupancy' => [
                'labels' => $occupancy->pluck('name')->values()->all(),
                'values' => $occupancy->pluck('total')->values()->all(),
            ],
            'residents' => [
                'labels' => $charts['refugeesByCamp']->pluck('name')->values()->all(),
                'values' => $charts['refugeesByCamp']->pluck('total')->values()->all(),
            ],
            'daily' => [
                'labels' => $dailyActivity->pluck('label')->values()->all(),
                'values' => $dailyActivity->pluck('total')->values()->all(),
            ],
            'aid' => [
                'labels' => $charts['aidByType']->pluck('name')->values()->all(),
                'values' => $charts['aidByType']->pluck('total')->values()->all(),
            ],
            'medical' => [
                'labels' => $charts['medicalByMonth']->pluck('name')->values()->all(),
                'values' => $charts['medicalByMonth']->pluck('total')->values()->all(),
            ],
            'security' => [
                'labels' => $charts['securityBySeverity']->pluck('name')->values()->all(),
                'values' => $charts['securityBySeverity']->pluck('total')->values()->all(),
            ],
        ];
    }

    private function visibleUnreadNotificationCount(?User $user): int
    {
        return (int) Notification::visibleFor($user)
            ->where('status', 'unread')
            ->selectRaw(
                "COUNT(DISTINCT CONCAT_WS('|', COALESCE(type, 'NULL'), COALESCE(title, 'NULL'), COALESCE(body, 'NULL'), COALESCE(related_type, 'NULL'), COALESCE(CAST(related_id AS CHAR), 'NULL'))) as aggregate"
            )
            ->value('aggregate');
    }

    private function recentActivity(): Collection
    {
        $items = collect();

        Refugee::latest()->limit(4)->get()->each(fn (Refugee $refugee) => $items->push([
            'icon' => 'user-plus',
            'title' => 'تسجيل لاجئ',
            'meta' => $refugee->full_name,
            'time' => $refugee->created_at,
            'tone' => 'good',
        ]));

        AidDistribution::with('aidType')->latest()->limit(4)->get()->each(fn (AidDistribution $aid) => $items->push([
            'icon' => 'package-check',
            'title' => 'توزيع مساعدة',
            'meta' => $aid->aidType?->name ?? 'مساعدة',
            'time' => $aid->created_at,
            'tone' => 'info',
        ]));

        MedicalRecord::with('refugee')->latest()->limit(4)->get()->each(fn (MedicalRecord $record) => $items->push([
            'icon' => 'stethoscope',
            'title' => 'سجل طبي',
            'meta' => $record->refugee?->full_name ?? 'لاجئ',
            'time' => $record->created_at,
            'tone' => $record->needs_follow_up ? 'warning' : 'good',
        ]));

        SecurityReport::with('refugee')->latest()->limit(4)->get()->each(fn (SecurityReport $report) => $items->push([
            'icon' => 'shield-alert',
            'title' => 'تقرير أمني',
            'meta' => ($report->refugee?->full_name ?? 'لاجئ').' / '.$report->severity,
            'time' => $report->created_at,
            'tone' => in_array($report->severity, ['high', 'critical'], true) ? 'danger' : 'warning',
        ]));

        EntryExitLog::with('refugee')->latest()->limit(4)->get()->each(fn (EntryExitLog $movement) => $items->push([
            'icon' => 'route',
            'title' => $movement->movement_type === 'entry' ? 'دخول للمخيم' : 'خروج من المخيم',
            'meta' => $movement->refugee?->full_name ?? 'لاجئ',
            'time' => $movement->created_at,
            'tone' => 'info',
        ]));

        ResidencyTransfer::with('refugee')->latest()->limit(4)->get()->each(fn (ResidencyTransfer $transfer) => $items->push([
            'icon' => 'move',
            'title' => 'انتقال سكني',
            'meta' => $transfer->refugee?->full_name ?? 'لاجئ',
            'time' => $transfer->created_at,
            'tone' => 'info',
        ]));

        return $items
            ->sortByDesc(fn (array $item) => $item['time'] instanceof Carbon ? $item['time']->timestamp : 0)
            ->take(8)
            ->values();
    }

    private function buildInsights(array $stats, float $occupancyPercentage): array
    {
        return [
            [
                'title' => 'ضغط السكن',
                'value' => $stats['unassigned'].' بدون سكن',
                'tone' => $stats['unassigned'] > 0 ? 'warning' : 'good',
                'body' => $stats['unassigned'] > 0 ? 'يفضل معالجة قائمة غير المخصص لهم قبل استقبال دفعة جديدة.' : 'كل اللاجئين الحاليين لديهم حالة سكن مستقرة.',
            ],
            [
                'title' => 'إشغال الوحدات',
                'value' => $occupancyPercentage.'%',
                'tone' => $occupancyPercentage >= 90 ? 'danger' : ($occupancyPercentage >= 70 ? 'warning' : 'good'),
                'body' => $occupancyPercentage >= 90 ? 'السعة قريبة من الحد الأعلى، راقب الوحدات الممتلئة.' : 'الإشغال ضمن مستوى قابل للإدارة.',
            ],
            [
                'title' => 'متابعة طبية',
                'value' => $stats['followups'].' حالة',
                'tone' => $stats['followups'] > 0 ? 'warning' : 'good',
                'body' => $stats['followups'] > 0 ? 'توجد حالات تحتاج موعد متابعة طبي.' : 'لا توجد متابعات طبية مفتوحة حاليًا.',
            ],
            [
                'title' => 'مخاطر أمنية',
                'value' => $stats['high_security'].' عالية/حرجة',
                'tone' => $stats['high_security'] > 0 ? 'danger' : 'good',
                'body' => $stats['high_security'] > 0 ? 'راجع تقارير الأمن عالية الخطورة.' : 'لا توجد تقارير عالية الخطورة حاليًا.',
            ],
        ];
    }
}
