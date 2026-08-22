<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Refugee;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public function index(Request $request): View
    {
        $rows = Household::with('head')
            ->withCount('members')
            ->when($request->q, function ($query, string $q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('household_code', 'like', '%'.$q.'%')
                        ->orWhereHas('head', function ($headQuery) use ($q): void {
                            $headQuery->where('first_name', 'like', '%'.$q.'%')
                                ->orWhere('father_name', 'like', '%'.$q.'%')
                                ->orWhere('last_name', 'like', '%'.$q.'%')
                                ->orWhere('document_number', 'like', '%'.$q.'%');
                        });
                });
            })
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('households.index', compact('rows'));
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

    public function addMember(Request $request, Household $household, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'refugee_id' => ['required', 'exists:refugees,id'],
            'relation_to_head' => ['required', 'string', 'max:255'],
        ]);

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
                ['name' => 'head_of_household_id', 'label' => 'رب الأسرة', 'type' => 'select', 'options' => Refugee::orderBy('first_name')->get()->pluck('full_name', 'id')],
                ['name' => 'status', 'label' => 'الحالة', 'type' => 'select', 'required' => true, 'options' => ['active' => 'فعالة', 'archived' => 'مؤرشفة']],
                ['name' => 'notes', 'label' => 'ملاحظات', 'type' => 'textarea'],
            ],
        ]);
    }
}
