<?php

namespace App\Http\Controllers;

use App\Models\AidDistribution;
use App\Models\Camp;
use App\Models\EntryExitLog;
use App\Models\Household;
use App\Models\MedicalRecord;
use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use App\Models\SecurityReport;
use App\Models\Shelter;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('reports.index', [
            'report' => $request->get('report', 'refugees'),
            'camps' => Camp::pluck('name', 'id'),
            'rows' => $this->dataset($request)->paginate(25)->withQueryString(),
        ]);
    }

    public function export(Request $request, AuditLogService $auditLog): Response
    {
        $rows = $this->dataset($request)->limit(5000)->get();
        $report = $request->get('report', 'refugees');
        $csv = $this->toCsv($rows);

        $auditLog->log('export', 'reports', null, 'تصدير تقرير '.$report, 'high', $request->all());

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$report.'_report.csv"',
        ]);
    }

    public function printable(Request $request, AuditLogService $auditLog): View
    {
        $report = $request->get('report', 'refugees');
        $rows = $this->dataset($request)->limit(1000)->get();

        $auditLog->log('print', 'reports', null, 'طباعة تقرير '.$report, 'high', $request->all());

        return view('reports.print', [
            'report' => $report,
            'rows' => $rows,
            'filters' => $request->only(['camp_id', 'from', 'to']),
        ]);
    }

    private function dataset(Request $request)
    {
        $report = $request->get('report', 'refugees');
        $campId = $request->get('camp_id');
        $from = $request->get('from');
        $to = $request->get('to');
        $this->authorizeReport($report);

        return match ($report) {
            'households' => Household::query()->with('head')->withCount('members'),
            'shelters' => Shelter::query()->with('camp')->withCount('refugees')->when($campId, fn ($q) => $q->where('camp_id', $campId)),
            'transfers' => ResidencyTransfer::query()->with('refugee')->when($from, fn ($q) => $q->whereDate('transferred_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('transferred_at', '<=', $to)),
            'aid' => AidDistribution::query()->with(['aidType', 'refugee', 'household', 'camp'])->when($campId, fn ($q) => $q->where('camp_id', $campId))->when($from, fn ($q) => $q->whereDate('distribution_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('distribution_date', '<=', $to)),
            'medical' => $this->medicalReportQuery($campId, $from, $to),
            'movement' => EntryExitLog::query()->with(['refugee', 'camp', 'checkpoint'])->when($campId, fn ($q) => $q->where('camp_id', $campId))->when($from, fn ($q) => $q->whereDate('movement_datetime', '>=', $from))->when($to, fn ($q) => $q->whereDate('movement_datetime', '<=', $to)),
            'security' => SecurityReport::query()->with(['refugee', 'camp'])->when($campId, fn ($q) => $q->where('camp_id', $campId))->when($from, fn ($q) => $q->whereDate('report_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('report_date', '<=', $to)),
            default => Refugee::query()->with(['currentCamp', 'currentShelter', 'household'])->when($campId, fn ($q) => $q->where('current_camp_id', $campId)),
        };
    }

    private function authorizeReport(string $report): void
    {
        $user = auth()->user();

        $roles = [
            'medical' => ['admin', 'medical_officer', 'manager'],
            'security' => ['admin', 'security_officer', 'manager'],
            'aid' => ['admin', 'aid_officer', 'manager'],
            'shelters' => ['admin', 'housing_officer', 'manager'],
            'transfers' => ['admin', 'housing_officer', 'manager'],
            'movement' => ['admin', 'security_officer', 'manager'],
        ];

        if (isset($roles[$report]) && ! $user->hasAnyRole($roles[$report])) {
            abort(403, 'لا تملك صلاحية هذا التقرير.');
        }
    }

    private function medicalReportQuery(?string $campId, ?string $from, ?string $to)
    {
        $query = MedicalRecord::query()
            ->with(['refugee', 'medicalService', 'camp'])
            ->when($campId, fn ($q) => $q->where('camp_id', $campId))
            ->when($from, fn ($q) => $q->whereDate('record_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('record_date', '<=', $to));

        if (auth()->user()->hasRole('manager') && ! auth()->user()->hasAnyRole(['admin', 'medical_officer'])) {
            $query->select(['id', 'refugee_id', 'medical_service_id', 'camp_id', 'record_date', 'needs_follow_up', 'follow_up_date']);
        }

        return $query;
    }

    private function toCsv(Collection $rows): string
    {
        if ($rows->isEmpty()) {
            return "\xEF\xBB\xBFلا توجد بيانات\n";
        }

        $data = $rows->map(fn ($row) => collect($row->toArray())->except(['created_at', 'updated_at'])->flatten()->all());
        $max = $data->map(fn ($row) => count($row))->max();
        $headers = collect(range(1, $max))->map(fn ($i) => 'حقل '.$i)->all();
        $lines = ["\xEF\xBB\xBF".implode(',', $headers)];

        foreach ($data as $row) {
            $lines[] = collect($row)
                ->map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"')
                ->implode(',');
        }

        return implode("\n", $lines);
    }
}
