<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Models\Refugee;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "كم عدد السكان في مخيم الزعتري؟" / "كم لاجئًا لدينا؟"
 */
class PopulationIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const COUNTING = ['كم', 'عدد', 'مجموع', 'إجمالي', 'إحصائية', 'إحصائيات'];

    /** @var list<string> */
    private const SUBJECTS = [
        'سكان', 'لاجئ', 'لاجئين', 'مقيمين', 'مقيم', 'نفوس', 'شخص', 'أشخاص',
        'أفراد', 'قاطنين', 'يعيش', 'يقيم', 'يسكن', 'ساكن', 'مسجلين',
    ];

    public function name(): string
    {
        return 'population';
    }

    public function group(): string
    {
        return 'registration';
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::COUNTING) || ! $query->hasAny(self::SUBJECTS)) {
            return null;
        }

        // Naming a camp is a third signal, which is what separates this from the
        // system-wide overview when both could answer.
        return $this->campReference($query)->isResolved() ? 3 : 2;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $reference = $this->campReference($query);

        if ($reference->isUnknown()) {
            return $this->unknownCamp($this->name(), $reference);
        }

        $camp = $reference->camp;

        $base = Refugee::query()->where('status', 'active');

        if ($camp !== null) {
            $base->where('current_camp_id', $camp->id);
        }

        $total = (clone $base)->count();

        if ($total === 0) {
            return Answer::empty(
                $this->name(),
                $camp !== null
                    ? 'لا يوجد سكان فعّالون مسجّلون في '.$reference->label().' حاليًا.'
                    : 'لا يوجد سكان فعّالون مسجّلون في النظام حتى الآن.',
            );
        }

        $inside = (clone $base)->where('presence_status', 'inside')->count();
        $housed = (clone $base)->where('housing_status', 'assigned')->count();

        $figures = [
            ['label' => 'إجمالي السكان', 'value' => number_format($total)],
            ['label' => 'داخل المخيم', 'value' => number_format($inside)],
            ['label' => 'خارج المخيم', 'value' => number_format($total - $inside)],
            ['label' => 'مسكَّنون', 'value' => number_format($housed)],
            ['label' => 'بلا سكن', 'value' => number_format($total - $housed)],
        ];

        return Answer::make(
            $this->name(),
            $camp !== null
                ? 'عدد السكان الفعّالين في '.$reference->label().' هو '.number_format($total).' شخصًا.'
                : 'إجمالي السكان الفعّالين في النظام '.number_format($total).' شخصًا.',
            $camp === null ? $this->perCamp() : [],
            $figures,
            [['label' => 'فتح قائمة السكان', 'url' => $this->listUrl($camp?->id), 'icon' => 'users-round']],
            ['كم لاجئًا بلا سكن؟'],
        );
    }

    /**
     * The camps carrying the population, largest first, so a system-wide answer
     * says where the people actually are.
     *
     * @return list<array{title: string, subtitle: string, meta: string, url: string}>
     */
    private function perCamp(): array
    {
        return Refugee::query()
            ->where('status', 'active')
            ->whereNotNull('current_camp_id')
            ->selectRaw('current_camp_id, count(*) as total')
            ->groupBy('current_camp_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('currentCamp')
            ->get()
            ->map(fn (Refugee $row): array => [
                'title' => $row->currentCamp?->name ?? '—',
                'subtitle' => 'سكان فعّالون',
                'meta' => number_format((int) $row->total),
                'url' => $this->listUrl($row->current_camp_id),
            ])
            ->pipe(fn (Collection $rows) => $rows->all());
    }

    private function listUrl(?int $campId): string
    {
        return route('refugees.index', array_filter([
            'status' => 'active',
            'camp_id' => $campId,
        ]));
    }

    public function examples(): array
    {
        return ['كم عدد السكان في {camp}؟', 'كم عدد اللاجئين المسجلين؟'];
    }
}
