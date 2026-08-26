<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Models\User;
use App\Support\RoleScope;

/**
 * "ابحث عن أحمد الحسن" / "من هو صاحب الوثيقة 12345"
 */
class RefugeeLookupIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const TRIGGERS = ['ابحث', 'بحث', 'اعثر', 'من هو', 'من هي', 'معلومات', 'بيانات', 'ملف', 'سجل', 'لاجئ', 'اللاجئ'];

    public function name(): string
    {
        return 'refugee_lookup';
    }

    public function group(): string
    {
        return RoleScope::LOOKUP;
    }

    public function score(AssistantQuery $query): ?int
    {
        $asking = $query->hasAny(self::TRIGGERS);
        $identifier = $query->codes() !== [] || $query->numbers() !== [];

        // Bare text with no search verb and no identifier is not claimed at all.
        // It falls through to the global search, which already resolves loose
        // text across refugees, households, shelters and camps — and answering
        // "لم أجد لاجئًا باسم شكرا جزيلا" would be a confident wrong reading.
        if (! $asking && ! $identifier) {
            return null;
        }

        return ($asking ? 1 : 0) + ($identifier ? 1 : 0);
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $matches = $this->refugeesIn($query, self::TRIGGERS, 6);
        $subject = $query->subject(self::TRIGGERS) ?: $query->raw;

        if ($matches->isEmpty()) {
            return Answer::empty(
                $this->name(),
                'لم أجد أي لاجئ يطابق «'.$subject.'». جرّب رقم الوثيقة، أو جزءًا من الاسم فقط.',
                ['كم عدد السكان؟', 'كم لاجئًا بلا سكن؟'],
            );
        }

        $text = $matches->count() === 1
            ? 'وجدت سجلًا واحدًا مطابقًا.'
            : 'وجدت '.$matches->count().' سجلات مطابقة لـ «'.$subject.'».';

        return Answer::make(
            $this->name(),
            $text,
            $matches->map(fn ($refugee) => $this->refugeeItem($refugee))->all(),
            followUps: ['أين يسكن '.($matches->first()->first_name ?? '').'؟'],
        );
    }

    public function examples(): array
    {
        return ['ابحث عن أحمد الحسن', 'من هو صاحب الوثيقة DOC12345678'];
    }
}
