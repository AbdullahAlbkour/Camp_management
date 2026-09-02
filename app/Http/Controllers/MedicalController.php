<?php

namespace App\Http\Controllers;

use App\Http\Requests\MedicalRecordRequest;
use App\Http\Requests\MedicalServiceRequest;
use App\Models\MedicalRecord;
use App\Models\MedicalService;
use App\Services\AuditLogService;
use App\Services\MedicalRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MedicalController extends Controller
{
    public function services(): View
    {
        return view('crud.index', [
            'title' => 'الخدمات الطبية',
            'createRoute' => route('medical.services.create'),
            'columns' => [
                ['label' => 'الاسم', 'field' => 'name'],
                ['label' => 'الحالة', 'field' => 'status'],
            ],
            'rows' => MedicalService::latest()->paginate(20),
            'editRoute' => 'medical.services.edit',
            'deleteResource' => 'medical_services',
        ]);
    }

    public function createService(): View
    {
        return $this->serviceForm(new MedicalService, route('medical.services.store'), 'POST', 'إضافة خدمة طبية');
    }

    public function storeService(MedicalServiceRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validated();

        $service = MedicalService::create($data);
        $auditLog->log('create', 'medical_services', $service, 'إضافة خدمة طبية', 'medium', $data);

        return redirect()->route('medical.services')->with('success', 'تم حفظ الخدمة الطبية.');
    }

    public function editService(MedicalService $medicalService): View
    {
        return $this->serviceForm($medicalService, route('medical.services.update', $medicalService), 'PUT', 'تعديل خدمة طبية');
    }

    public function updateService(MedicalServiceRequest $request, MedicalService $medicalService, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validated();

        $medicalService->update($data);
        $auditLog->log('update', 'medical_services', $medicalService, 'تعديل خدمة طبية', 'medium', $data);

        return redirect()->route('medical.services')->with('success', 'تم تعديل الخدمة الطبية.');
    }

    public function records(): View
    {
        return view('crud.index', [
            'title' => 'السجلات الطبية',
            'createRoute' => route('medical.records.create'),
            'columns' => [
                ['label' => 'اللاجئ', 'field' => 'refugee.full_name'],
                ['label' => 'الخدمة', 'field' => 'medicalService.name'],
                ['label' => 'المخيم', 'field' => 'camp.name'],
                ['label' => 'التاريخ', 'field' => 'record_date'],
                ['label' => 'متابعة', 'field' => 'needs_follow_up'],
            ],
            'rows' => MedicalRecord::with(['refugee', 'medicalService', 'camp'])->latest()->paginate(20),
            'editRoute' => 'medical.records.edit',
        ]);
    }

    public function createRecord(): View
    {
        return $this->recordForm(new MedicalRecord, route('medical.records.store'), 'POST', 'إضافة سجل طبي');
    }

    public function storeRecord(MedicalRecordRequest $request, MedicalRecordService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->create($data);

        return redirect()->route('medical.records')->with('success', 'تم حفظ السجل الطبي.');
    }

    public function editRecord(MedicalRecord $medicalRecord): View
    {
        return $this->recordForm($medicalRecord, route('medical.records.update', $medicalRecord), 'PUT', 'تعديل سجل طبي');
    }

    public function updateRecord(MedicalRecordRequest $request, MedicalRecord $medicalRecord, MedicalRecordService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->update($medicalRecord, $data);

        return redirect()->route('medical.records')->with('success', 'تم تعديل السجل الطبي.');
    }

    private function serviceForm(MedicalService $service, string $action, string $method, string $title): View
    {
        return view('crud.form', [
            'title' => $title,
            'action' => $action,
            'method' => $method,
            'backRoute' => route('medical.services'),
            'model' => $service,
            'fields' => [
                ['name' => 'name', 'label' => 'اسم الخدمة', 'type' => 'text', 'required' => true],
                ['name' => 'status', 'label' => 'الحالة', 'type' => 'select', 'required' => true, 'options' => ['active' => 'فعال', 'inactive' => 'غير فعال']],
                ['name' => 'description', 'label' => 'الوصف', 'type' => 'textarea'],
            ],
        ]);
    }

    private function recordForm(MedicalRecord $record, string $action, string $method, string $title): View
    {
        return view('crud.form', [
            'title' => $title,
            'action' => $action,
            'method' => $method,
            'backRoute' => route('medical.records'),
            'model' => $record,
            'fields' => [
                ['name' => 'refugee_id', 'label' => 'اللاجئ', 'type' => 'async-refugee', 'required' => true, 'url' => route('lookups.refugees'), 'placeholder' => 'ابحث بالاسم أو الوثيقة أو الهاتف', 'display' => $record->refugee?->full_name],
                ['name' => 'medical_service_id', 'label' => 'الخدمة الطبية', 'type' => 'select', 'required' => true, 'options' => MedicalService::where('status', 'active')->pluck('name', 'id')],
                ['name' => 'record_date', 'label' => 'تاريخ السجل', 'type' => 'date', 'required' => true, 'value' => now()->toDateString()],
                ['name' => 'diagnosis', 'label' => 'التشخيص', 'type' => 'textarea', 'required' => true],
                ['name' => 'notes', 'label' => 'ملاحظات طبية', 'type' => 'textarea'],
                ['name' => 'needs_follow_up', 'label' => 'تحتاج متابعة', 'type' => 'checkbox'],
                ['name' => 'follow_up_date', 'label' => 'تاريخ المتابعة', 'type' => 'date'],
            ],
        ]);
    }
}
