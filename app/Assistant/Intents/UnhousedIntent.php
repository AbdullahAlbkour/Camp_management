<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Models\Refugee;
use App\Models\User;

/**
 * "كم لاجئًا بلا سكن؟" / "من ليس لديه سكن في مخيم الزعتري؟"
 */
class UnhousedIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const TRIGGERS = [
        'بلا سكن', 'بدون سكن', 'دون سكن', 'بلا مسكن', 'بدون مسكن', 'غير مسكن',
        'غير مسكنين', 'غير مخصص', 'بلا مأوى', 'ليس لديه سكن', 'ليس لديهم سكن',
        'ينتظرون سكن', 'بانتظار سكن',
    ];

    public function name(): string
    {
        return 'unhoused';
    }

    public function group(): string
    {
        return 'housing';
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::TRIGGERS)) {
            return null;
        }

        // The phrase is specific enough on its own; a named camp only narrows it.
        return $this->campReference($query)->isResolved() ? 4 : 3;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $reference = $this->campReference($query);

        if ($reference->isUnknown()) {
            return $this->unknownCamp($this->name(), $reference);
        }

        $camp = $reference->camp;

        $base = Refugee::query()
            ->where('status', 'active')
            ->where('housing_status', 'unassigned');

        if ($camp !== null) {
            $base->where('current_camp_id', $camp->id);
        }

        $total = (clone $base)->count();

        if ($total === 0) {
            return Answer::empty(
                $this->name(),
                $camp !== null
                    ? 'لا يوجد أحد بلا سكن في '.$reference->label().' — كل السكان الفعّالين مخصص لهم سكن.'
                    : 'لا يوجد أحد بلا سكن حاليًا — كل السكان الفعّالين مخصص لهم سكن.',
            );
        }

        // Waiting longest first: this list exists to be worked through, and the
        // person registered earliest has been waiting the longest.
        $waiting = (clone $base)
            ->with(['currentCamp', 'currentShelter'])
            ->orderBy('created_at')
            ->limit(5)
            ->get();

        $oldest = $waiting->first();

        $figures = [['label' => 'بلا سكن', 'value' => number_format($total)]];

        if ($oldest?->created_at !== null) {
            $figures[] = ['label' => 'أطول انتظار', 'value' => (int) $oldest->created_at->diffInDays(now()).' يوم'];
        }

        return Answer::make(
            $this->name(),
            $camp !== null
                ? number_format($total).' من سكان '.$reference->label().' بلا سكن حاليًا.'
                : number_format($total).' من السكان الفعّالين بلا سكن حاليًا.',
            $waiting->map(fn (Refugee $refugee) => $this->refugeeItem($refugee))->all(),
            $figures,
            $this->workLink($user),
        );
    }

    /**
     * The queue screen is a housing action, so the link only appears for roles
     * that can act on it.
     *
     * @return list<array{label: string, url: string, icon: string}>
     */
    private function workLink(User $user): array
    {
        if (! $user->hasAnyRole(['admin', 'housing_officer'])) {
            return [];
        }

        return [['label' => 'فتح قائمة غير المسكَّنين', 'url' => route('housing.unassigned'), 'icon' => 'bed']];
    }

    public function examples(): array
    {
        return ['كم لاجئًا بلا سكن؟', 'من بلا سكن في {camp}؟'];
    }
}
