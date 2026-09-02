<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Assistant\TimeWindow;
use App\Models\Checkpoint;
use App\Models\EntryExitLog;
use App\Models\User;
use App\Support\ArabicText;
use Illuminate\Support\Carbon;

/**
 * "كم حركة عبر البوابة الرئيسية اليوم؟" / "كم عدد حركات الدخول والخروج؟"
 */
class CheckpointTrafficIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const SUBJECTS = ['حركة', 'حركات', 'دخول', 'خروج', 'مرور', 'عبور'];

    /** @var list<string> */
    private const COUNTING = ['كم', 'عدد', 'مجموع', 'إجمالي', 'إحصائية', 'إحصائيات'];

    public function name(): string
    {
        return 'checkpoint_traffic';
    }

    public function group(): string
    {
        return 'security';
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::SUBJECTS) || ! $query->hasAny(self::COUNTING)) {
            return null;
        }

        $signals = 3;

        if ($this->checkpointIn($query) !== null) {
            $signals++;
        }

        if (TimeWindow::in($query) !== null) {
            $signals++;
        }

        return $signals;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $checkpoint = $this->checkpointIn($query);

        // A gate was named and it is not in the register. Reporting that is the
        // only honest answer: falling through would count every other gate and
        // present the total as though it belonged to the one asked about.
        $named = $this->gateNameIn($query);

        if ($checkpoint === null && $named !== null) {
            return $this->unknownCheckpoint($named);
        }

        [$from, $to, $label] = TimeWindow::range(TimeWindow::in($query) ?? 'today');

        $base = EntryExitLog::query()
            ->whereBetween('movement_datetime', [$from, $to])
            ->when($checkpoint !== null, fn ($q) => $q->where('checkpoint_id', $checkpoint->id));

        $total = (clone $base)->count();
        $where = $checkpoint !== null ? ' عبر '.$checkpoint->name : '';

        if ($total === 0) {
            return Answer::empty(
                $this->name(),
                'لم تُسجَّل أي حركة'.$where.' '.$label.'.',
            );
        }

        $entries = (clone $base)->where('movement_type', 'entry')->count();

        return Answer::make(
            $this->name(),
            number_format($total).' حركة مسجّلة'.$where.' '.$label.'.',
            $checkpoint === null ? $this->perCheckpoint($from, $to) : [],
            [
                ['label' => 'إجمالي الحركات', 'value' => number_format($total)],
                ['label' => 'دخول', 'value' => number_format($entries)],
                ['label' => 'خروج', 'value' => number_format($total - $entries)],
                ['label' => 'الفترة', 'value' => $label],
            ],
            [['label' => 'سجل الحركة', 'url' => route('security.movements'), 'icon' => 'door-open']],
        );
    }

    /**
     * The name written after a gate word, or null when no particular gate is
     * named — "كم حركة عبر البوابات اليوم" asks about all of them, and has no
     * name that could fail to resolve.
     *
     * At most two words are taken, and the run stops at a time phrase so
     * "البوابة الرئيسية اليوم" does not report a gate called "الرئيسية اليوم".
     */
    private function gateNameIn(AssistantQuery $query): ?string
    {
        // The words being compared come from the folded question, so the lists
        // are folded too — raw "بوابة" never equals the folded "بوابه".
        $gates = array_map(ArabicText::normalize(...), ['بوابة', 'نقطة', 'معبر']);
        $stop = array_map(ArabicText::normalize(...), [
            'اليوم', 'أمس', 'الأسبوع', 'الشهر', 'السنة', 'العام', 'الحالي', 'الماضي', 'هذا', 'هذه', 'كل', 'تفتيش',
        ]);
        $parts = [];

        foreach ($query->words as $index => $word) {
            $bare = preg_replace('/^(و|ف|ب|ل|ك)?(ال)?/u', '', $word) ?? $word;

            if (! in_array($bare, $gates, true)) {
                continue;
            }

            for ($i = $index + 1; $i < count($query->words) && count($parts) < 2; $i++) {
                $next = $query->words[$i];

                // "نقطة تفتيش" is the noun itself; the gate's name comes after.
                if ($next === ArabicText::normalize('تفتيش') && $parts === []) {
                    continue;
                }

                if (in_array($next, $stop, true) || preg_match('/^\d+$/u', $next) === 1) {
                    break;
                }

                $parts[] = $query->rawWords[$i] ?? $next;
            }

            break;
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @return list<array{title: string, subtitle: string, meta: string}>
     */
    private function perCheckpoint(Carbon $from, Carbon $to): array
    {
        return EntryExitLog::query()
            ->whereBetween('movement_datetime', [$from, $to])
            ->selectRaw('checkpoint_id, count(*) as total')
            ->groupBy('checkpoint_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('checkpoint.camp')
            ->get()
            ->map(fn (EntryExitLog $row): array => [
                'title' => $row->checkpoint?->name ?? 'نقطة غير محددة',
                'subtitle' => $row->checkpoint?->camp?->name ?? '—',
                'meta' => number_format((int) $row->total).' حركة',
            ])
            ->values()
            ->all();
    }

    private function unknownCheckpoint(string $named): Answer
    {
        return $this->unknownNamed(
            $this->name(),
            'لا توجد نقطة تفتيش باسم «'.$named.'» في النظام.',
            'نقاط التفتيش المسجّلة حاليًا:',
            Checkpoint::query()->with('camp')->orderBy('name')->limit(8)->get(),
            fn (Checkpoint $checkpoint): array => [
                'title' => $checkpoint->name,
                'subtitle' => $checkpoint->camp?->name ?? '—',
                'meta' => $checkpoint->location ?: '—',
            ],
        );
    }

    public function examples(): array
    {
        return ['كم حركة عبر البوابة الرئيسية اليوم؟'];
    }
}
