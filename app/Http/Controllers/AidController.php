<?php

namespace App\Http\Controllers;

use App\Models\AidDistribution;
use App\Models\AidType;
use App\Models\Camp;
use App\Models\Household;
use App\Models\Organization;
use App\Models\Refugee;
use App\Services\AidDistributionService;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AidController extends Controller
{
    public function organizations(): View
    {
        return view('crud.index', [
            'title' => 'الجهات الداعمة',
            'createRoute' => route('aid.organizations.create'),
            'columns' => [
                ['label' => 'الاسم', 'field' => 'name'],
                ['label' => 'المسؤول', 'field' => 'contact_name'],
                ['label' => 'الهاتف', 'field' => 'phone'],
                ['label' => 'الحالة', 'field' => 'status'],
            ],
            'rows' => Organization::latest()->paginate(20),
            'editRoute' => 'aid.organizations.edit',
        ]);
    }

    public function createOrganization(): View
    {
        return $this->organizationForm(new Organization, route('aid.organizations.store'), 'POST', 'إضافة جهة داعمة');
    }

    public function storeOrganization(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:organizations,name'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        $organization = Organization::create($data);
        $auditLog->log('create', 'organizations', $organization, 'إضافة جهة داعمة', 'medium', $data);

        return redirect()->route('aid.organizations')->with('success', 'تم حفظ الجهة الداعمة.');
    }

    public function editOrganization(Organization $organization): View
    {
        return $this->organizationForm($organization, route('aid.organizations.update', $organization), 'PUT', 'تعديل جهة داعمة');
    }

    public function updateOrganization(Request $request, Organization $organization, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:organizations,name,'.$organization->id],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        $organization->update($data);
        $auditLog->log('update', 'organizations', $organization, 'تعديل جهة داعمة', 'medium', $data);

        return redirect()->route('aid.organizations')->with('success', 'تم تعديل الجهة الداعمة.');
    }

    public function aidTypes(): View
    {
        return view('crud.index', [
            'title' => 'أنواع المساعدات',
            'createRoute' => route('aid.types.create'),
            'columns' => [
                ['label' => 'الاسم', 'field' => 'name'],
                ['label' => 'الجهة', 'field' => 'organization.name'],
                ['label' => 'الوحدة', 'field' => 'unit'],
                ['label' => 'الحالة', 'field' => 'status'],
            ],
            'rows' => AidType::with('organization')->latest()->paginate(20),
            'editRoute' => 'aid.types.edit',
        ]);
    }

    public function createAidType(): View
    {
        return $this->aidTypeForm(new AidType, route('aid.types.store'), 'POST', 'إضافة نوع مساعدة');
    }

    public function storeAidType(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $aidType = AidType::create($data);
        $auditLog->log('create', 'aid_types', $aidType, 'إضافة نوع مساعدة', 'medium', $data);

        return redirect()->route('aid.types')->with('success', 'تم حفظ نوع المساعدة.');
    }

    public function editAidType(AidType $aidType): View
    {
        return $this->aidTypeForm($aidType, route('aid.types.update', $aidType), 'PUT', 'تعديل نوع مساعدة');
    }

    public function updateAidType(Request $request, AidType $aidType, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $aidType->update($data);
        $auditLog->log('update', 'aid_types', $aidType, 'تعديل نوع مساعدة', 'medium', $data);

        return redirect()->route('aid.types')->with('success', 'تم تعديل نوع المساعدة.');
    }

    public function distributions(): View
    {
        $rows = AidDistribution::with(['aidType.organization', 'refugee', 'household', 'camp'])
            ->latest()
            ->paginate(20);

        return view('crud.index', [
            'title' => 'توزيع المساعدات',
            'createRoute' => route('aid.distributions.create'),
            'columns' => [
                ['label' => 'نوع المساعدة', 'field' => 'aidType.name'],
                ['label' => 'لاجئ', 'field' => 'refugee.full_name'],
                ['label' => 'أسرة', 'field' => 'household.household_code'],
                ['label' => 'المخيم', 'field' => 'camp.name'],
                ['label' => 'الكمية', 'field' => 'quantity'],
                ['label' => 'التاريخ', 'field' => 'distribution_date'],
            ],
            'rows' => $rows,
        ]);
    }

    public function createDistribution(): View
    {
        return view('crud.form', [
            'title' => 'توزيع مساعدة',
            'action' => route('aid.distributions.store'),
            'method' => 'POST',
            'backRoute' => route('aid.distributions'),
            'model' => new AidDistribution,
            'fields' => [
                ['name' => 'aid_type_id', 'label' => 'نوع المساعدة', 'type' => 'select', 'required' => true, 'options' => AidType::where('status', 'active')->pluck('name', 'id')],
                ['name' => 'refugee_id', 'label' => 'اللاجئ المستفيد', 'type' => 'async-refugee', 'url' => route('lookups.refugees'), 'placeholder' => 'ابحث عن لاجئ بالاسم أو الوثيقة'],
                ['name' => 'household_id', 'label' => 'الأسرة المستفيدة', 'type' => 'async-household', 'url' => route('lookups.households'), 'placeholder' => 'ابحث عن أسرة بالرمز أو رب الأسرة'],
                ['name' => 'camp_id', 'label' => 'المخيم وقت التوزيع (اختياري)', 'type' => 'select', 'options' => Camp::pluck('name', 'id')],
                ['name' => 'quantity', 'label' => 'الكمية', 'type' => 'number', 'required' => true, 'step' => '0.01'],
                ['name' => 'distribution_date', 'label' => 'تاريخ التوزيع', 'type' => 'date', 'required' => true, 'value' => now()->toDateString()],
                ['name' => 'notes', 'label' => 'ملاحظات', 'type' => 'textarea'],
            ],
            'hint' => 'اختر لاجئًا أو أسرة فقط، وليس الاثنين معًا.',
        ]);
    }

    public function storeDistribution(Request $request, AidDistributionService $service): RedirectResponse
    {
        $data = $request->validate([
            'aid_type_id' => ['required', 'exists:aid_types,id'],
            'refugee_id' => ['nullable', 'exists:refugees,id'],
            'household_id' => ['nullable', 'exists:households,id'],
            'camp_id' => ['nullable', 'exists:camps,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'distribution_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $service->distribute($data);

        return redirect()->route('aid.distributions')->with('success', 'تم تسجيل توزيع المساعدة.');
    }

    private function organizationForm(Organization $organization, string $action, string $method, string $title): View
    {
        return view('crud.form', [
            'title' => $title,
            'action' => $action,
            'method' => $method,
            'backRoute' => route('aid.organizations'),
            'model' => $organization,
            'fields' => [
                ['name' => 'name', 'label' => 'اسم الجهة', 'type' => 'text', 'required' => true],
                ['name' => 'contact_name', 'label' => 'الشخص المسؤول', 'type' => 'text'],
                ['name' => 'phone', 'label' => 'الهاتف', 'type' => 'text'],
                ['name' => 'email', 'label' => 'البريد', 'type' => 'email'],
                ['name' => 'status', 'label' => 'الحالة', 'type' => 'select', 'required' => true, 'options' => ['active' => 'فعال', 'inactive' => 'غير فعال']],
                ['name' => 'notes', 'label' => 'ملاحظات', 'type' => 'textarea'],
            ],
        ]);
    }

    private function aidTypeForm(AidType $aidType, string $action, string $method, string $title): View
    {
        return view('crud.form', [
            'title' => $title,
            'action' => $action,
            'method' => $method,
            'backRoute' => route('aid.types'),
            'model' => $aidType,
            'fields' => [
                ['name' => 'organization_id', 'label' => 'الجهة الداعمة', 'type' => 'select', 'options' => Organization::where('status', 'active')->pluck('name', 'id')],
                ['name' => 'name', 'label' => 'اسم المساعدة', 'type' => 'text', 'required' => true],
                ['name' => 'unit', 'label' => 'الوحدة', 'type' => 'text', 'required' => true],
                ['name' => 'status', 'label' => 'الحالة', 'type' => 'select', 'required' => true, 'options' => ['active' => 'فعال', 'inactive' => 'غير فعال']],
                ['name' => 'description', 'label' => 'الوصف', 'type' => 'textarea'],
            ],
        ]);
    }
}
