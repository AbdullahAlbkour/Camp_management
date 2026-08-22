<?php

namespace App\Http\Controllers;

use App\Models\Camp;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampController extends Controller
{
    public function index(): View
    {
        $rows = Camp::withCount(['refugees', 'shelters'])->latest()->paginate(20);

        return view('crud.index', [
            'title' => 'المخيمات',
            'createRoute' => route('camps.create'),
            'columns' => [
                ['label' => 'الاسم', 'field' => 'name'],
                ['label' => 'الموقع', 'field' => 'location'],
                ['label' => 'السعة', 'field' => 'capacity'],
                ['label' => 'اللاجئون', 'field' => 'refugees_count'],
                ['label' => 'الوحدات', 'field' => 'shelters_count'],
                ['label' => 'الحالة', 'field' => 'status'],
            ],
            'rows' => $rows,
            'editRoute' => 'camps.edit',
        ]);
    }

    public function create(): View
    {
        return $this->form(new Camp, route('camps.store'), 'POST', 'إضافة مخيم');
    }

    public function store(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $camp = Camp::create($data);
        $auditLog->log('create', 'camps', $camp, 'إضافة مخيم', 'medium', $data);

        return redirect()->route('camps.index')->with('success', 'تم حفظ المخيم.');
    }

    public function edit(Camp $camp): View
    {
        return $this->form($camp, route('camps.update', $camp), 'PUT', 'تعديل مخيم');
    }

    public function update(Request $request, Camp $camp, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate($this->rules($camp->id));
        $camp->update($data);
        $auditLog->log('update', 'camps', $camp, 'تعديل مخيم', 'medium', $data);

        return redirect()->route('camps.index')->with('success', 'تم تعديل المخيم.');
    }

    private function rules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:camps,name'.($id ? ','.$id : '')],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function form(Camp $camp, string $action, string $method, string $title): View
    {
        return view('crud.form', [
            'title' => $title,
            'action' => $action,
            'method' => $method,
            'backRoute' => route('camps.index'),
            'model' => $camp,
            'fields' => [
                ['name' => 'name', 'label' => 'اسم المخيم', 'type' => 'text', 'required' => true],
                ['name' => 'location', 'label' => 'الموقع', 'type' => 'text'],
                ['name' => 'capacity', 'label' => 'السعة', 'type' => 'number', 'required' => true],
                ['name' => 'status', 'label' => 'الحالة', 'type' => 'select', 'required' => true, 'options' => ['active' => 'فعال', 'inactive' => 'غير فعال']],
                ['name' => 'notes', 'label' => 'ملاحظات', 'type' => 'textarea'],
            ],
        ]);
    }
}
