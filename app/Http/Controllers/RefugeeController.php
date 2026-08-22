<?php

namespace App\Http\Controllers;

use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use App\Services\AuditLogService;
use App\Services\HousingService;
use App\Services\RefugeeRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RefugeeController extends Controller
{
    public function index(Request $request): View
    {
        $rows = Refugee::with(['currentCamp', 'currentShelter', 'household'])
            ->when($request->q, function ($query, string $q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('document_number', 'like', '%'.$q.'%')
                        ->orWhere('first_name', 'like', '%'.$q.'%')
                        ->orWhere('father_name', 'like', '%'.$q.'%')
                        ->orWhere('last_name', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%');
                });
            })
            ->when($request->camp_id, fn ($query, $campId) => $query->where('current_camp_id', $campId))
            ->when($request->housing_status, fn ($query, $status) => $query->where('housing_status', $status))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('refugees.index', [
            'rows' => $rows,
            'camps' => Camp::pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('refugees.form', [
            'title' => 'تسجيل لاجئ جديد',
            'refugee' => new Refugee,
            'action' => route('refugees.store'),
            'method' => 'POST',
            'camps' => Camp::pluck('name', 'id'),
            'shelters' => Shelter::with('camp')->get(),
            'households' => Household::pluck('household_code', 'id'),
        ]);
    }

    public function store(Request $request, RefugeeRegistrationService $service): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $duplicates = $service->possibleDuplicates($data);

        if ($duplicates->isNotEmpty() && ! $request->boolean('confirmed_duplicate_check')) {
            return back()
                ->withInput()
                ->with('warning', 'توجد سجلات مشابهة. راجع النتائج ثم أكد المتابعة كتسجيل جديد.')
                ->with('duplicates', $duplicates);
        }

        $refugee = $service->register($data);

        return redirect()->route('refugees.show', $refugee)->with('success', 'تم تسجيل اللاجئ بنجاح.');
    }

    public function show(Refugee $refugee): View
    {
        $refugee->load([
            'currentCamp',
            'currentShelter',
            'household.head',
            'residencyTransfers',
            'aidDistributions.aidType.organization',
            'medicalRecords.medicalService',
            'entryExitLogs.checkpoint',
            'securityReports',
            'attachments.uploadedBy',
        ]);

        return view('refugees.show', [
            'refugee' => $refugee,
            'householdAid' => $refugee->household?->aidDistributions()->with('aidType.organization')->latest()->get() ?? collect(),
        ]);
    }

    public function edit(Refugee $refugee): View
    {
        return view('refugees.form', [
            'title' => 'تعديل بيانات لاجئ',
            'refugee' => $refugee,
            'action' => route('refugees.update', $refugee),
            'method' => 'PUT',
            'camps' => Camp::pluck('name', 'id'),
            'shelters' => Shelter::with('camp')->get(),
            'households' => Household::pluck('household_code', 'id'),
        ]);
    }

    public function update(
        Request $request,
        Refugee $refugee,
        AuditLogService $auditLog,
        HousingService $housing
    ): RedirectResponse {
        $data = $request->validate($this->rules($refugee->id));

        // Housing is never written straight to the row: it goes through HousingService so
        // capacity is enforced and a residency_transfers entry is recorded for the move.
        $campId = (int) $data['current_camp_id'];
        $shelterId = $data['current_shelter_id'] ?? null;
        unset($data['current_camp_id'], $data['current_shelter_id'], $data['housing_status']);

        DB::transaction(function () use ($refugee, $data, $campId, $shelterId, $housing, $auditLog): void {
            $refugee->update($data);

            $housing->transferRefugee(
                $refugee,
                $campId,
                $shelterId === null ? null : (int) $shelterId,
                'تعديل بيانات اللاجئ',
                'current_shelter_id'
            );

            $auditLog->log('update', 'refugees', $refugee, 'تعديل بيانات لاجئ', 'high', $data);
        });

        return redirect()->route('refugees.show', $refugee)->with('success', 'تم تعديل بيانات اللاجئ.');
    }

    private function rules(?int $id = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255', 'unique:refugees,document_number'.($id ? ','.$id : '')],
            'phone' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'current_camp_id' => ['required', 'exists:camps,id'],
            'current_shelter_id' => ['nullable', 'exists:shelters,id'],
            'presence_status' => ['nullable', 'in:inside,outside'],
            'household_id' => ['nullable', 'exists:households,id'],
            'relation_to_head' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
