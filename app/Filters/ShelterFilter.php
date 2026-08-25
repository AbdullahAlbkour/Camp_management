<?php

namespace App\Filters;

use App\Models\Camp;
use App\Models\Refugee;
use App\Support\ArabicText;
use App\Support\Labels;
use App\Support\SearchExpression;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ShelterFilter extends Filter
{
    public function sortable(): array
    {
        return [
            'code' => 'code',
            'capacity' => 'capacity',
            'occupied' => 'occupied',
            'created' => 'created_at',
        ];
    }

    public function defaultSort(): array
    {
        return ['camp_id' => 'asc', 'code' => 'asc'];
    }

    public function fields(): array
    {
        return [
            [
                'name' => 'q',
                'label' => 'بحث',
                'type' => 'search',
                'placeholder' => 'رمز الوحدة أو ملاحظاتها',
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
                'name' => 'type',
                'label' => 'النوع',
                'type' => 'select',
                'placeholder' => 'كل الأنواع',
                'options' => Labels::map('shelter_type'),
            ],
            [
                'name' => 'status',
                'label' => 'الحالة',
                'type' => 'select',
                'placeholder' => 'كل الحالات',
                'options' => ['active' => 'فعالة', 'maintenance' => 'صيانة', 'inactive' => 'غير فعالة'],
            ],
            [
                'name' => 'occupancy',
                'label' => 'الإشغال',
                'type' => 'select',
                'placeholder' => 'الجميع',
                'options' => [
                    'available' => 'فيها متسع',
                    'full' => 'ممتلئة',
                    'empty' => 'فارغة تمامًا',
                ],
            ],
            [
                'name' => 'capacity_min',
                'label' => 'السعة من',
                'type' => 'number',
                'placeholder' => '1',
                'min' => 1,
                'narrow' => true,
            ],
        ];
    }

    public function apply(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('q'), fn (Builder $q) => $this->search($q, (string) $request->get('q')))
            ->when($request->filled('camp_id'), fn (Builder $q) => $q->where('camp_id', $request->get('camp_id')))
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->get('type')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->get('status')))
            ->when($request->filled('capacity_min'), fn (Builder $q) => $q->where('capacity', '>=', (int) $request->get('capacity_min')))
            ->when($request->filled('occupancy'), fn (Builder $q) => $this->byOccupancy($q, (string) $request->get('occupancy')));
    }

    /**
     * Occupancy compares the live occupant count against the unit's own capacity
     * column, so it needs a correlated subquery rather than a HAVING on the
     * withCount alias: paginate() runs a count(*) that discards the select list,
     * and the alias no longer exists by then.
     */
    private function byOccupancy(Builder $query, string $mode): Builder
    {
        if ($mode === 'empty') {
            return $query->whereDoesntHave('refugees', fn ($r) => $r->where('status', 'active'));
        }

        $occupants = Refugee::query()
            ->selectRaw('count(*)')
            ->whereColumn('refugees.current_shelter_id', 'shelters.id')
            ->where('refugees.status', 'active');

        $comparison = $mode === 'full' ? '>=' : '<';

        return $query->whereRaw(
            '('.$occupants->toSql().') '.$comparison.' '.$query->getQuery()->getGrammar()->wrap('shelters.capacity'),
            $occupants->getBindings()
        );
    }

    /**
     * Unit codes and notes are Latin or short free text; lowercasing is enough.
     */
    private function search(Builder $query, string $term): Builder
    {
        $like = '%'.ArabicText::normalize($term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner
                ->whereRaw(SearchExpression::lower('code').' LIKE ?', [$like])
                ->orWhereRaw(SearchExpression::lower('notes').' LIKE ?', [$like]);
        });
    }
}
