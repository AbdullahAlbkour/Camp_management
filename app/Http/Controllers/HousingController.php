<?php

namespace App\Http\Controllers;

use App\Http\Requests\HousingTransferRequest;
use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use App\Services\HousingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HousingController extends Controller
{
    public function unassigned(): View
    {
        $rows = Refugee::with('currentCamp')
            ->where('housing_status', 'unassigned')
            ->latest()
            ->paginate(20);

        return view('housing.unassigned', compact('rows'));
    }

    public function transferForm(Refugee $refugee): View
    {
        return view('housing.transfer', [
            'refugee' => $refugee->load(['currentCamp', 'currentShelter']),
            'camps' => Camp::pluck('name', 'id'),
            'shelters' => Shelter::with('camp')->where('status', 'active')->get(),
            'action' => route('housing.transfer', $refugee),
        ]);
    }

    public function transfer(HousingTransferRequest $request, Refugee $refugee, HousingService $housing): RedirectResponse
    {
        $data = $request->validated();

        $housing->transferRefugee($refugee, (int) $data['camp_id'], $data['shelter_id'] ? (int) $data['shelter_id'] : null, $data['reason'] ?? null);

        return redirect()->route('refugees.show', $refugee)->with('success', 'تم تنفيذ عملية السكن أو الانتقال.');
    }

    public function householdTransfer(HousingTransferRequest $request, Household $household, HousingService $housing): RedirectResponse
    {
        $data = $request->validated();

        $housing->transferHousehold($household, (int) $data['camp_id'], $data['shelter_id'] ? (int) $data['shelter_id'] : null, $data['reason'] ?? null);

        return redirect()->route('households.show', $household)->with('success', 'تم نقل أفراد الأسرة.');
    }
}
