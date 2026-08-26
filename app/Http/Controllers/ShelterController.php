<?php

namespace App\Http\Controllers;

use App\Filters\ShelterFilter;
use App\Http\Requests\ShelterRequest;
use App\Models\Camp;
use App\Models\Shelter;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShelterController extends Controller
{
    public function index(Request $request, ShelterFilter $filter): View
    {
        $rows = $filter->paginate(
            Shelter::query()
                ->with('camp')
                ->withCount(['refugees as occupied' => fn ($query) => $query->where('status', 'active')]),
            $request
        );

        return view('shelters.index', [
            'rows' => $rows,
            'filter' => $filter,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Shelter, route('shelters.store'), 'POST', 'إضافة وحدة سكنية');
    }

    public function store(ShelterRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validated();
        $shelter = Shelter::create($data);
        $auditLog->log('create', 'shelters', $shelter, 'إضافة وحدة سكنية', 'medium', $data);

        return redirect()->route('shelters.index')->with('success', 'تم حفظ الوحدة السكنية.');
    }

    public function edit(Shelter $shelter): View
    {
        return $this->form($shelter, route('shelters.update', $shelter), 'PUT', 'تعديل وحدة سكنية');
    }

    public function update(ShelterRequest $request, Shelter $shelter, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validated();
        $shelter->update($data);
        $auditLog->log('update', 'shelters', $shelter, 'تعديل وحدة سكنية', 'medium', $data);

        return redirect()->route('shelters.index')->with('success', 'تم تعديل الوحدة السكنية.');
    }

    private function form(Shelter $shelter, string $action, string $method, string $title): View
    {
        return view('crud.form', [
            'title' => $title,
            'action' => $action,
            'method' => $method,
            'backRoute' => route('shelters.index'),
            'model' => $shelter,
            'fields' => [
                ['name' => 'camp_id', 'label' => 'المخيم', 'type' => 'select', 'required' => true, 'options' => Camp::pluck('name', 'id')],
                ['name' => 'code', 'label' => 'رمز الوحدة', 'type' => 'text', 'required' => true],
                ['name' => 'type', 'label' => 'النوع', 'type' => 'select', 'required' => true, 'options' => ['tent' => 'خيمة', 'room' => 'غرفة', 'caravan' => 'كرفان']],
                ['name' => 'capacity', 'label' => 'السعة', 'type' => 'number', 'required' => true],
                ['name' => 'status', 'label' => 'الحالة', 'type' => 'select', 'required' => true, 'options' => ['active' => 'فعال', 'maintenance' => 'صيانة', 'inactive' => 'غير فعال']],
                ['name' => 'notes', 'label' => 'ملاحظات', 'type' => 'textarea'],
            ],
        ]);
    }
}
