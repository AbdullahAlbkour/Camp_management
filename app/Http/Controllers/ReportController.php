<?php

namespace App\Http\Controllers;

use App\Models\Camp;
use App\Reports\ReportDefinition;
use App\Reports\ReportExporter;
use App\Reports\ReportRegistry;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly ReportExporter $exporter,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(Request $request): View
    {
        $report = $this->resolve($request);

        return view('reports.index', [
            'report' => $report,
            'available' => $this->registry->availableFor($request->user()),
            'camps' => Camp::orderBy('name')->pluck('name', 'id'),
            'rows' => $report->query->paginate(25)->withQueryString(),
            'filters' => $this->filters($request),
        ]);
    }

    public function export(Request $request): Response
    {
        $report = $this->resolve($request);
        $format = $request->get('format') === 'csv' ? 'csv' : 'xlsx';

        // Exporting lifts protected personal data out of the system, so it is always
        // recorded, including which filters decided who ended up in the file.
        $this->auditLog->log(
            'export',
            'reports',
            null,
            'تصدير تقرير '.$report->label.' بصيغة '.strtoupper($format),
            'high',
            $this->filters($request) + ['format' => $format]
        );

        return $format === 'csv'
            ? $this->exporter->csv($report)
            : $this->exporter->xlsx($report);
    }

    public function printable(Request $request): View
    {
        $report = $this->resolve($request);

        $this->auditLog->log(
            'print',
            'reports',
            null,
            'طباعة تقرير '.$report->label,
            'high',
            $this->filters($request)
        );

        return view('reports.print', [
            'report' => $report,
            'rows' => $report->query->limit(2000)->get(),
            'filters' => $this->filters($request),
            'camps' => Camp::pluck('name', 'id'),
        ]);
    }

    /**
     * Build the requested report, refusing keys the signed-in user may not run.
     */
    private function resolve(Request $request): ReportDefinition
    {
        $user = $request->user();
        $key = (string) $request->get('report', ReportRegistry::DEFAULT_REPORT);

        if (! array_key_exists($key, $this->registry->availableFor($user))) {
            abort(403, 'لا تملك صلاحية هذا التقرير.');
        }

        return $this->registry->build($key, $this->filters($request), $user);
    }

    /**
     * @return array{camp_id: string|null, from: string|null, to: string|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'camp_id' => ['nullable', 'integer', 'exists:camps,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            'camp_id' => $validated['camp_id'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }
}
