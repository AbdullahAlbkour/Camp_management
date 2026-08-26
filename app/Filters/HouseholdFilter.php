<?php

namespace App\Filters;

use App\Models\Camp;
use App\Support\ArabicText;
use App\Support\SearchExpression;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HouseholdFilter extends Filter
{
    public function sortable(): array
    {
        return [
            'code' => 'household_code',
            'members' => 'members_count',
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
                'placeholder' => 'رمز الأسرة، اسم رب الأسرة، أو رقم وثيقته',
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
                'name' => 'status',
                'label' => 'الحالة',
                'type' => 'select',
                'placeholder' => 'كل الحالات',
                'options' => ['active' => 'فعالة', 'archived' => 'مؤرشفة'],
            ],
            [
                'name' => 'housing',
                'label' => 'السكن',
                'type' => 'select',
                'placeholder' => 'الجميع',
                'options' => [
                    'housed' => 'جميع أفرادها مسكنون',
                    'partial' => 'بعض أفرادها بلا سكن',
                    'unhoused' => 'لا أحد منها مسكن',
                ],
            ],
            [
                'name' => 'members_min',
                'label' => 'عدد الأفراد من',
                'type' => 'number',
                'placeholder' => '0',
                'min' => 0,
                'narrow' => true,
            ],
            [
                'name' => 'members_max',
                'label' => 'إلى',
                'type' => 'number',
                'placeholder' => '20',
                'min' => 0,
                'narrow' => true,
            ],
            [
                'name' => 'no_head',
                'label' => 'بلا رب أسرة',
                'type' => 'toggle',
                'narrow' => true,
            ],
        ];
    }

    public function apply(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('q'), fn (Builder $q) => $this->search($q, (string) $request->get('q')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->get('status')))
            // A household has no camp of its own; it is wherever its members are.
            ->when($request->filled('camp_id'), fn (Builder $q) => $q->whereHas(
                'members',
                fn ($member) => $member->where('current_camp_id', $request->get('camp_id'))
            ))
            // has() compares a correlated count inside WHERE. having() on the
            // withCount alias looks equivalent but breaks the count(*) query
            // paginate() issues, because that query drops the select list.
            ->when($request->filled('members_min'), fn (Builder $q) => $q->has('members', '>=', (int) $request->get('members_min')))
            ->when($request->filled('members_max'), fn (Builder $q) => $q->has('members', '<=', (int) $request->get('members_max')))
            ->when($request->boolean('no_head'), fn (Builder $q) => $q->whereNull('head_of_household_id'))
            ->when($request->filled('housing'), fn (Builder $q) => $this->byHousing($q, (string) $request->get('housing')));
    }

    private function byHousing(Builder $query, string $mode): Builder
    {
        $unhoused = fn ($member) => $member->where('status', 'active')->where('housing_status', 'unassigned');
        $housed = fn ($member) => $member->where('status', 'active')->where('housing_status', 'assigned');

        return match ($mode) {
            'housed' => $query->whereHas('members', $housed)->whereDoesntHave('members', $unhoused),
            'unhoused' => $query->whereHas('members', $unhoused)->whereDoesntHave('members', $housed),
            'partial' => $query->whereHas('members', $unhoused)->whereHas('members', $housed),
            default => $query,
        };
    }

    /**
     * Household codes are Latin, so they only need lowercasing; the head's name
     * is matched through the refugee's folded blob.
     */
    private function search(Builder $query, string $term): Builder
    {
        $folded = ArabicText::normalize($term);
        $like = '%'.$folded.'%';

        return $query->where(function (Builder $inner) use ($like, $folded): void {
            $inner
                ->whereRaw(SearchExpression::lower('household_code').' LIKE ?', [$like])
                ->orWhereHas('head', fn ($head) => $head->where('search_text', 'like', '%'.$folded.'%'));
        });
    }
}
