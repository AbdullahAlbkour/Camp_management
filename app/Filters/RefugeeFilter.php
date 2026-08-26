<?php

namespace App\Filters;

use App\Models\Camp;
use App\Support\ArabicText;
use App\Support\Labels;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RefugeeFilter extends Filter
{
    public function sortable(): array
    {
        return [
            'name' => 'first_name',
            'document' => 'document_number',
            'birth' => 'date_of_birth',
            'created' => 'created_at',
        ];
    }

    public function defaultSort(): array
    {
        return ['created_at' => 'desc'];
    }

    public function fields(): array
    {
        return [
            [
                'name' => 'q',
                'label' => 'بحث',
                'type' => 'search',
                'placeholder' => 'الاسم، رقم الوثيقة، الهاتف، أو رمز البطاقة',
                'wide' => true,
            ],
            [
                'name' => 'camp_id',
                'label' => 'المخيم',
                'type' => 'select',
                'placeholder' => 'كل المخيمات',
                'options' => Camp::orderBy('name')->pluck('name', 'id')->all(),
            ],
            [
                'name' => 'housing_status',
                'label' => 'حالة السكن',
                'type' => 'select',
                'placeholder' => 'كل حالات السكن',
                'options' => Labels::map('housing_status'),
            ],
            [
                'name' => 'presence_status',
                'label' => 'الوجود',
                'type' => 'select',
                'placeholder' => 'داخل وخارج',
                'options' => Labels::map('presence_status'),
            ],
            [
                'name' => 'gender',
                'label' => 'الجنس',
                'type' => 'select',
                'placeholder' => 'الجميع',
                'options' => Labels::map('gender'),
            ],
            [
                'name' => 'status',
                'label' => 'حالة السجل',
                'type' => 'select',
                'placeholder' => 'كل الحالات',
                'options' => Labels::map('refugee_status'),
            ],
            [
                'name' => 'age_min',
                'label' => 'العمر من',
                'type' => 'number',
                'placeholder' => '0',
                'min' => 0,
                'max' => 120,
                'narrow' => true,
            ],
            [
                'name' => 'age_max',
                'label' => 'العمر إلى',
                'type' => 'number',
                'placeholder' => '120',
                'min' => 0,
                'max' => 120,
                'narrow' => true,
            ],
            [
                'name' => 'unhoused_days',
                'label' => 'بلا سكن منذ (يوم)',
                'type' => 'number',
                'placeholder' => 'مثال: 7',
                'min' => 1,
                'narrow' => true,
            ],
        ];
    }

    public function apply(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('q'), fn (Builder $q) => $this->search($q, (string) $request->get('q')))
            ->when($request->filled('camp_id'), fn (Builder $q) => $q->where('current_camp_id', $request->get('camp_id')))
            ->when($request->filled('shelter_id'), fn (Builder $q) => $q->where('current_shelter_id', $request->get('shelter_id')))
            ->when($request->filled('housing_status'), fn (Builder $q) => $q->where('housing_status', $request->get('housing_status')))
            ->when($request->filled('presence_status'), fn (Builder $q) => $q->where('presence_status', $request->get('presence_status')))
            ->when($request->filled('gender'), fn (Builder $q) => $q->where('gender', $request->get('gender')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->get('status')))
            // Age filters read as "at least this old" / "at most this old", which
            // inverts against date_of_birth: an older person has an earlier date.
            ->when($request->filled('age_min'), fn (Builder $q) => $q
                ->whereNotNull('date_of_birth')
                ->whereDate('date_of_birth', '<=', now()->subYears((int) $request->get('age_min'))->toDateString()))
            ->when($request->filled('age_max'), fn (Builder $q) => $q
                ->whereNotNull('date_of_birth')
                ->whereDate('date_of_birth', '>', now()->subYears((int) $request->get('age_max') + 1)->toDateString()))
            ->when($request->filled('unhoused_days'), fn (Builder $q) => $q
                ->where('housing_status', 'unassigned')
                ->where('created_at', '<=', now()->subDays((int) $request->get('unhoused_days'))));
    }

    /**
     * Match against the pre-folded search blob.
     *
     * The blob already carries the name, document number and phone folded the
     * same way ArabicText folds the term, so one LIKE covers all of them and
     * "احمد" finds "أحمد" without any per-row work.
     */
    private function search(Builder $query, string $term): Builder
    {
        $folded = ArabicText::normalize($term);

        return $query->where(function (Builder $inner) use ($folded): void {
            $inner->where('search_text', 'like', '%'.$folded.'%');

            // Badge codes read REF-000123; accept the bare id or the full code.
            if (preg_match('/(\d+)/', $folded, $matches)) {
                $inner->orWhere('id', (int) ltrim($matches[1], '0') ?: 0);
            }
        });
    }
}
