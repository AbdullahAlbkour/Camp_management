<?php

namespace App\Http\Controllers;

use App\Models\Camp;
use App\Models\Checkpoint;
use App\Models\EntryExitLog;
use App\Models\SecurityReport;
use App\Services\AuditLogService;
use App\Services\MovementSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function checkpoints(): View
    {
        return view('crud.index', [
            'title' => 'نقاط التفتيش',
            'createRoute' => route('security.checkpoints.create'),
            'columns' => [
                ['label' => 'الاسم', 'field' => 'name'],
                ['label' => 'المخيم', 'field' => 'camp.name'],
                ['label' => 'الموقع', 'field' => 'location'],
                ['label' => 'الحالة', 'field' => 'status'],
            ],
            'rows' => Checkpoint::with('camp')->latest()->paginate(20),
            'editRoute' => 'security.checkpoints.edit',
        ]);
    }

    public function createCheckpoint(): View
    {
        return $this->checkpointForm(new Checkpoint, route('security.checkpoints.store'), 'POST', 'إضافة نقطة تفتيش');
    }

    public function storeCheckpoint(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'camp_id' => ['required', 'exists:camps,id'],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $checkpoint = Checkpoint::create($data);
        $auditLog->log('create', 'checkpoints', $checkpoint, 'إضافة نقطة تفتيش', 'medium', $data);

        return redirect()->route('security.checkpoints')->with('success', 'تم حفظ نقطة التفتيش.');
    }

    public function editCheckpoint(Checkpoint $checkpoint): View
    {
        return $this->checkpointForm($checkpoint, route('security.checkpoints.update', $checkpoint), 'PUT', 'تعديل نقطة تفتيش');
    }

    public function updateCheckpoint(Request $request, Checkpoint $checkpoint, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'camp_id' => ['required', 'exists:camps,id'],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $checkpoint->update($data);
        $auditLog->log('update', 'checkpoints', $checkpoint, 'تعديل نقطة تفتيش', 'medium', $data);

        return redirect()->route('security.checkpoints')->with('success', 'تم تعديل نقطة التفتيش.');
    }

    public function movements(): View
    {
        return view('crud.index', [
            'title' => 'حركة الدخول والخروج',
            'createRoute' => route('security.movements.create'),
            'columns' => [
                ['label' => 'اللاجئ', 'field' => 'refugee.full_name'],
                ['label' => 'المخيم', 'field' => 'camp.name'],
                ['label' => 'نقطة التفتيش', 'field' => 'checkpoint.name'],
                ['label' => 'النوع', 'field' => 'movement_type'],
                ['label' => 'التاريخ والوقت', 'field' => 'movement_datetime'],
            ],
            'rows' => EntryExitLog::with(['refugee', 'checkpoint', 'camp'])->latest()->paginate(20),
        ]);
    }

    public function createMovement(): View
    {
        return view('crud.form', [
            'title' => 'تسجيل حركة دخول/خروج',
            'action' => route('security.movements.store'),
            'method' => 'POST',
            'backRoute' => route('security.movements'),
            'model' => new EntryExitLog,
            'fields' => [
                ['name' => 'refugee_id', 'label' => 'اللاجئ', 'type' => 'async-refugee', 'required' => true, 'url' => route('lookups.refugees'), 'placeholder' => 'ابحث بالاسم أو الوثيقة أو الهاتف'],
                ['name' => 'checkpoint_id', 'label' => 'نقطة التفتيش', 'type' => 'select', 'required' => true, 'options' => Checkpoint::with('camp')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name.' - '.$c->camp?->name])],
                ['name' => 'movement_type', 'label' => 'نوع الحركة', 'type' => 'select', 'required' => true, 'options' => ['entry' => 'دخول', 'exit' => 'خروج']],
                ['name' => 'movement_datetime', 'label' => 'التاريخ والوقت', 'type' => 'datetime-local', 'required' => true, 'value' => now()->format('Y-m-d\TH:i')],
                ['name' => 'reason', 'label' => 'السبب', 'type' => 'textarea'],
            ],
        ]);
    }

    public function storeMovement(Request $request, MovementSecurityService $service): RedirectResponse
    {
        $data = $request->validate([
            'refugee_id' => ['required', 'exists:refugees,id'],
            'checkpoint_id' => ['required', 'exists:checkpoints,id'],
            'movement_type' => ['required', 'in:entry,exit'],
            'movement_datetime' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
        ]);

        $service->recordMovement($data);

        return redirect()->route('security.movements')->with('success', 'تم تسجيل الحركة.');
    }

    public function reports(): View
    {
        return view('crud.index', [
            'title' => 'التقارير الأمنية',
            'createRoute' => route('security.reports.create'),
            'columns' => [
                ['label' => 'اللاجئ', 'field' => 'refugee.full_name'],
                ['label' => 'المخيم', 'field' => 'camp.name'],
                ['label' => 'نوع الحادثة', 'field' => 'incident_type'],
                ['label' => 'الخطورة', 'field' => 'severity'],
                ['label' => 'التاريخ', 'field' => 'report_date'],
            ],
            'rows' => SecurityReport::with(['refugee', 'camp'])->latest()->paginate(20),
        ]);
    }

    public function createReport(): View
    {
        return view('crud.form', [
            'title' => 'إضافة تقرير أمني',
            'action' => route('security.reports.store'),
            'method' => 'POST',
            'backRoute' => route('security.reports'),
            'model' => new SecurityReport,
            'fields' => [
                ['name' => 'refugee_id', 'label' => 'اللاجئ', 'type' => 'async-refugee', 'required' => true, 'url' => route('lookups.refugees'), 'placeholder' => 'ابحث بالاسم أو الوثيقة أو الهاتف'],
                ['name' => 'incident_type', 'label' => 'نوع الحادثة', 'type' => 'text', 'required' => true],
                ['name' => 'severity', 'label' => 'درجة الخطورة', 'type' => 'select', 'required' => true, 'options' => ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'critical' => 'حرجة']],
                ['name' => 'report_date', 'label' => 'تاريخ التقرير', 'type' => 'date', 'required' => true, 'value' => now()->toDateString()],
                ['name' => 'description', 'label' => 'الوصف', 'type' => 'textarea', 'required' => true],
                ['name' => 'action_taken', 'label' => 'الإجراء المتخذ', 'type' => 'textarea'],
            ],
        ]);
    }

    public function storeReport(Request $request, MovementSecurityService $service): RedirectResponse
    {
        $data = $request->validate([
            'refugee_id' => ['required', 'exists:refugees,id'],
            'incident_type' => ['required', 'string', 'max:255'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'report_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'action_taken' => ['nullable', 'string'],
        ]);

        $service->createSecurityReport($data);

        return redirect()->route('security.reports')->with('success', 'تم حفظ التقرير الأمني.');
    }

    private function checkpointForm(Checkpoint $checkpoint, string $action, string $method, string $title): View
    {
        return view('crud.form', [
            'title' => $title,
            'action' => $action,
            'method' => $method,
            'backRoute' => route('security.checkpoints'),
            'model' => $checkpoint,
            'fields' => [
                ['name' => 'camp_id', 'label' => 'المخيم', 'type' => 'select', 'required' => true, 'options' => Camp::pluck('name', 'id')],
                ['name' => 'name', 'label' => 'اسم النقطة', 'type' => 'text', 'required' => true],
                ['name' => 'location', 'label' => 'الموقع', 'type' => 'text'],
                ['name' => 'status', 'label' => 'الحالة', 'type' => 'select', 'required' => true, 'options' => ['active' => 'فعال', 'inactive' => 'غير فعال']],
            ],
        ]);
    }
}
