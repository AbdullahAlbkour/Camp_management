<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Refugee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function refugees(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));

        $rows = Refugee::with(['currentCamp', 'currentShelter'])
            ->when($request->boolean('unassigned'), fn ($query) => $query->whereNull('household_id'))
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('first_name', 'like', '%'.$term.'%')
                        ->orWhere('father_name', 'like', '%'.$term.'%')
                        ->orWhere('last_name', 'like', '%'.$term.'%')
                        ->orWhere('document_number', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('first_name')
            ->limit(20)
            ->get()
            ->map(fn (Refugee $refugee) => [
                'id' => $refugee->id,
                'text' => $refugee->full_name,
                'meta' => trim(($refugee->document_number ?: 'بدون وثيقة').' / '.($refugee->currentCamp?->name ?: 'بدون مخيم').' / '.($refugee->currentShelter?->display_name ?: 'بدون سكن')),
            ]);

        return response()->json($rows);
    }

    public function households(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));

        $rows = Household::with('head')
            ->when($term !== '', function ($query) use ($term): void {
                $query->where('household_code', 'like', '%'.$term.'%')
                    ->orWhereHas('head', function ($inner) use ($term): void {
                        $inner->where('first_name', 'like', '%'.$term.'%')
                            ->orWhere('father_name', 'like', '%'.$term.'%')
                            ->orWhere('last_name', 'like', '%'.$term.'%')
                            ->orWhere('document_number', 'like', '%'.$term.'%');
                    });
            })
            ->orderBy('household_code')
            ->limit(20)
            ->get()
            ->map(fn (Household $household) => [
                'id' => $household->id,
                'text' => $household->household_code,
                'meta' => 'رب الأسرة: '.($household->head?->full_name ?: 'غير محدد'),
            ]);

        return response()->json($rows);
    }
}
