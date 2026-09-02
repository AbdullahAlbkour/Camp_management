<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Models\EntryExitLog;
use App\Models\Refugee;
use App\Models\User;
use App\Support\Labels;

/**
 * "متى آخر حركة لأحمد؟" / "متى خرج أحمد من المخيم؟"
 */
class LastMovementIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const TRIGGERS = [
        'كانت', 'كان', 'سجل', 'حالة',
        'حركة', 'حركات', 'دخول', 'خروج', 'مرور', 'متى', 'آخر', 'اخر', 'دخل', 'خرج', 'سجل الحركة',
    ];

    /** @var list<string> */
    private const COUNTING = ['كم', 'عدد', 'إحصائية', 'إحصائيات', 'مجموع', 'نسبة'];

    public function name(): string
    {
        return 'last_movement';
    }

    /**
     * The movement log lives behind the security screens, so the question does
     * too — unlike the presence column, which the refugee profile already shows.
     */
    public function group(): string
    {
        return 'security';
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::TRIGGERS)) {
            return null;
        }

        // "كم حركة عبر البوابة اليوم" is a count over a gate, not a person's
        // history; CheckpointTrafficIntent answers that one.
        if ($query->hasAny(self::COUNTING)) {
            return null;
        }

        $identified = $query->codes() !== [] || $query->subject(self::TRIGGERS) !== '';

        return $identified ? 4 : null;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $triggers = array_merge(self::TRIGGERS, $this->campWords($query));
        $matches = $this->refugeesIn($query, $triggers, 4);

        if ($matches->isEmpty()) {
            $subject = $query->subject($triggers);

            return $subject === ''
                ? $this->noSubject($this->name())
                : Answer::empty($this->name(), 'لم أجد أي لاجئ يطابق «'.$subject.'» لأعرض حركاته.');
        }

        if ($matches->count() > 1) {
            return $this->tooManyPeople($this->name(), $matches, $query->subject($triggers));
        }

        /** @var Refugee $refugee */
        $refugee = $matches->first();

        $movements = $refugee->entryExitLogs()
            ->with('checkpoint')
            ->orderByDesc('movement_datetime')
            ->limit(5)
            ->get();

        if ($movements->isEmpty()) {
            return Answer::empty(
                $this->name(),
                'لا توجد حركات دخول أو خروج مسجّلة لـ'.$refugee->full_name.' حتى الآن.',
            );
        }

        /** @var EntryExitLog $last */
        $last = $movements->first();
        $when = $last->movement_datetime;

        return Answer::make(
            $this->name(),
            'آخر حركة لـ'.$refugee->full_name.' كانت '.Labels::get('movement_type', $last->movement_type)
                .' عبر '.($last->checkpoint?->name ?? 'نقطة غير محددة')
                .($when !== null ? ' بتاريخ '.$when->format('Y-m-d').' الساعة '.$when->format('H:i') : '').'.',
            $movements->map(fn (EntryExitLog $row): array => [
                'title' => Labels::get('movement_type', $row->movement_type)
                    .' • '.($row->checkpoint?->name ?? '—'),
                'subtitle' => $row->reason ?: 'بدون سبب مسجّل',
                'meta' => $row->movement_datetime?->format('Y-m-d H:i') ?? '—',
            ])->all(),
            [
                ['label' => 'الحالة الحالية', 'value' => Labels::get('presence_status', $refugee->presence_status)],
                ['label' => 'حركات مسجّلة', 'value' => number_format($refugee->entryExitLogs()->count())],
            ],
            [['label' => 'سجل الحركة', 'url' => route('security.movements'), 'icon' => 'door-open']],
        );
    }

    public function examples(): array
    {
        return ['متى كانت آخر حركة لأحمد الحسن؟'];
    }
}
