<?php

namespace App\Http\Controllers;

use App\Filters\HouseholdFilter;
use App\Http\Requests\HouseholdMemberRequest;
use App\Models\Household;
use App\Models\Refugee;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public function index(Request $request, HouseholdFilter $filter): View
    {
        $rows = $filter->paginate(
            Household::query()->with('head.currentCamp')->withCount('members'),
            $request
        );

        return view('households.index', [
            'rows' => $rows,
            'filter' => $filter,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Household, route('households.store'), 'POST', 'إنشاء أسرة');
    }

    public function store(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $household = Household::create($data);
        $auditLog->log('create', 'households', $household, 'إنشاء أسرة', 'medium', $data);

        if ($household->head_of_household_id) {
            Refugee::whereKey($household->head_of_household_id)->update([
                'household_id' => $household->id,
                'relation_to_head' => 'رب الأسرة',
            ]);
        }

        return redirect()->route('households.show', $household)->with('success', 'تم إنشاء الأسرة.');
    }

    public function show(Household $household): View
    {
        $household->load(['head', 'members.currentCamp', 'members.currentShelter', 'aidDistributions.aidType']);

        return view('households.show', [
            'household' => $household,
            'refugees' => Refugee::whereNull('household_id')->pluck('first_name', 'id'),
        ]);
    }

    public function edit(Household $household): View
    {
        return $this->form($household, route('households.update', $household), 'PUT', 'تعديل أسرة');
    }

    public function update(Request $request, Household $household, AuditLogService $auditLog): RedirectResponse
    {
        $oldHead = $household->head_of_household_id;
        $data = $request->validate($this->rules($household->id));
        $household->update($data);

        if ($oldHead !== $household->head_of_household_id) {
            if ($household->head_of_household_id) {
                Refugee::whereKey($household->head_of_household_id)->update([
                    'household_id' => $household->id,
                    'relation_to_head' => 'رب الأسرة',
                ]);
            }

            $auditLog->log('update_head', 'households', $household, 'تغيير رب الأسرة', 'high', [
                'old_head' => $oldHead,
                'new_head' => $household->head_of_household_id,
            ]);
        }

        return redirect()->route('households.show', $household)->with('success', 'تم تعديل الأسرة.');
    }

    public function addMember(HouseholdMemberRequest $request, Household $household, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validated();

        $refugee = Refugee::findOrFail($data['refugee_id']);
        $refugee->update([
            'household_id' => $household->id,
            'relation_to_head' => $data['relation_to_head'],
        ]);

        $auditLog->log('attach', 'households', $household, 'ربط لاجئ بأسرة', 'medium', $data);

        return back()->with('success', 'تمت إضافة الفرد إلى الأسرة.');
    }

    public function removeMember(Household $household, Refugee $refugee, AuditLogService $auditLog): RedirectResponse
    {
        if ((int) $refugee->household_id !== (int) $household->id) {
            abort(404);
        }

        $refugee->update(['household_id' => null, 'relation_to_head' => null]);
        $auditLog->log('detach', 'households', $household, 'فصل لاجئ عن أسرة', 'medium', ['refugee_id' => $refugee->id]);

        return back()->with('success', 'تم فصل الفرد عن الأسرة.');
    }

    private function rules(?int $id = null): array
    {
        return [
            'household_code' => ['required', 'string', 'max:255', 'unique:households,household_code'.($id ? ','.$id : '')],
            'head_of_household_id' => ['nullable', 'exists:refugees,id'],
            'status' => ['required', 'in:active,archived'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function form(Household $household, string $action, string $method, string $title): View
    {
        return view('crud.form', [
            'title' => $title,
            'action' => $action,
            'method' => $method,
            'backRoute' => route('households.index'),
            'model' => $household,
            'fields' => [
                ['name' => 'household_code', 'label' => 'رمز الأسرة', 'type' => 'text', 'required' => true],
                // Typed rather than picked from a list: the list held every
                // refugee in the system, which is thousands of options rendered
                // into one <select> and unusable long before that. The lookup
                // searches by name, document number or phone and still resolves
                // to a real record, because the column is a foreign key.
                [
                    'name' => 'head_of_household_id',
                    'label' => 'رب الأسرة',
                    'type' => 'async-refugee',
                    'url' => route('lookups.refugees'),
                    'placeholder' => 'ابحث بالاسم أو رقم الوثيقة أو الهاتف',
                    'display' => $household->head?->full_name,
                ],
                ['name' => 'status', 'label' => 'الحالة', 'type' => 'select', 'required' => true, 'options' => ['active' => 'فعالة', 'archived' => 'مؤرشفة']],
                ['name' => 'notes', 'label' => 'ملاحظات', 'type' => 'textarea'],
            ],
        ]);
    }
}
