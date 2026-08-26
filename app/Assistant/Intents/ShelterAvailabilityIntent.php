<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Models\Shelter;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "كم وحدة فارغة؟" / "ما الوحدات المتاحة في مخيم الزعتري؟"
 */
class ShelterAvailabilityIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const SUBJECTS = ['وحدة', 'وحدات', 'خيمة', 'خيام', 'كرفان', 'غرفة', 'غرف', 'مساكن', 'سكنية'];

    /** @var list<string> */
    private const QUALIFIERS = [
        'فارغة', 'فاضية', 'خالية', 'متاحة', 'متوفرة', 'شاغرة', 'شاغر',
        'ممتلئة', 'مكتملة', 'إشغال', 'سعة', 'متبقية', 'باقية',
    ];

    public function name(): string
    {
        return 'shelter_availability';
    }

    public function group(): string
    {
        return 'housing';
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::SUBJECTS) || ! $query->hasAny(self::QUALIFIERS)) {
            return null;
        }

        return $this->campIn($query) !== null ? 4 : 3;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $camp = $this->campIn($query);

        $shelters = Shelter::query()
            ->where('status', 'active')
            ->when($camp !== null, fn ($q) => $q->where('camp_id', $camp->id))
            ->with('camp')
            ->withCount(['refugees' => fn ($q) => $q->where('status', 'active')])
            ->get();

        if ($shelters->isEmpty()) {
            return Answer::empty(
                $this->name(),
                $camp !== null
                    ? 'لا توجد وحدات سكنية فعّالة مسجّلة في '.$this->campLabel($camp).'.'
                    : 'لا توجد وحدات سكنية فعّالة مسجّلة في النظام.',
            );
        }

        // The three states are mutually exclusive, so the counts add up to the
        // number of active units and no unit is reported twice.
        $full = $shelters->filter(fn (Shelter $s) => $s->refugees_count >= $s->capacity);
        $empty = $shelters->filter(fn (Shelter $s) => $s->refugees_count === 0);
        $partial = $shelters->count() - $full->count() - $empty->count();

        $freeSpaces = $shelters->sum(fn (Shelter $s) => max(0, (int) $s->capacity - $s->refugees_count));

        $figures = [
            ['label' => 'وحدات فارغة', 'value' => number_format($empty->count())],
            ['label' => 'مشغولة جزئيًا', 'value' => number_format(max(0, $partial))],
            ['label' => 'ممتلئة', 'value' => number_format($full->count())],
            ['label' => 'أماكن شاغرة', 'value' => number_format($freeSpaces)],
        ];

        $where = $camp !== null ? ' في '.$this->campLabel($camp) : '';

        return Answer::make(
            $this->name(),
            $empty->isEmpty()
                ? 'لا توجد وحدة فارغة تمامًا'.$where.'، لكن هناك '.number_format($freeSpaces).' مكانًا شاغرًا موزّعًا على وحدات مشغولة جزئيًا.'
                : number_format($empty->count()).' وحدة فارغة'.$where.'، بإجمالي '.number_format($freeSpaces).' مكان شاغر.',
            $this->availableItems($shelters, $canOpenShelters = $user->hasAnyRole(['admin', 'housing_officer'])),
            $figures,
            $canOpenShelters
                ? [['label' => 'فتح الوحدات السكنية', 'url' => $this->listUrl($camp?->id), 'icon' => 'home']]
                : [],
        );
    }

    /**
     * Units with room, emptiest first — the order someone assigning housing
     * actually wants to read them in.
     *
     * A manager may ask about occupancy but cannot open the shelter screens, so
     * the rows carry a link only for the roles that can actually follow it —
     * an unreachable link is a 403 waiting to happen.
     *
     * @param  Collection<int, Shelter>  $shelters
     * @return list<array{title: string, subtitle: string, meta: string, url?: string}>
     */
    private function availableItems(Collection $shelters, bool $withLinks): array
    {
        return $shelters
            ->filter(fn (Shelter $s) => $s->refugees_count < $s->capacity)
            ->sortByDesc(fn (Shelter $s) => (int) $s->capacity - $s->refugees_count)
            ->take(5)
            ->map(fn (Shelter $s): array => array_filter([
                'title' => $s->display_name,
                'subtitle' => $s->camp?->name ?? '—',
                'meta' => 'شاغر '.max(0, (int) $s->capacity - $s->refugees_count).' من '.$s->capacity,
                'url' => $withLinks ? route('shelters.edit', $s) : null,
            ]))
            ->values()
            ->all();
    }

    private function listUrl(?int $campId): string
    {
        return route('shelters.index', array_filter([
            'occupancy' => 'available',
            'camp_id' => $campId,
        ]));
    }

    public function examples(): array
    {
        return ['كم وحدة سكنية فارغة؟', 'ما الوحدات المتاحة في مخيم الزعتري؟'];
    }
}
