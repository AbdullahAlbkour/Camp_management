<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Models\Refugee;
use App\Models\User;
use App\Support\Labels;
use App\Support\RoleScope;

/**
 * "أين يسكن أحمد؟" / "ما حالة سكن الوثيقة 12345؟"
 */
class HousingStatusIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const TRIGGERS = [
        'أين', 'وين', 'مكان', 'موقع', 'الوحدة', 'الخيمة', 'سكن',
        // Every conjugation a clerk might type, because an unlisted form is not
        // stripped from the question and ends up searched as part of the name.
        'يسكن', 'تسكن', 'يسكنون', 'يقيم', 'تقيم', 'يعيش', 'تعيش', 'ساكن', 'ساكنة', 'مقيم', 'مقيمة',
    ];

    /** @var list<string> */
    private const COUNTING = ['كم', 'عدد', 'إحصائية', 'إحصائيات', 'مجموع', 'نسبة', 'قائمة'];

    public function name(): string
    {
        return 'housing_status';
    }

    public function group(): string
    {
        return RoleScope::LOOKUP;
    }

    public function score(AssistantQuery $query): ?int
    {
        // "كم شخصًا بلا سكن" is a counting question about the whole camp, not a
        // question about where one person lives, even though both say "سكن".
        if ($query->hasAny(self::COUNTING) || ! $query->hasAny(self::TRIGGERS)) {
            return null;
        }

        $identified = $query->codes() !== []
            || $query->numbers() !== []
            || $query->subject(self::TRIGGERS) !== '';

        return $identified ? 3 : null;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $matches = $this->refugeesIn($query, self::TRIGGERS, 4);

        if ($matches->isEmpty()) {
            $subject = $query->subject(self::TRIGGERS);

            return $subject === ''
                ? $this->noSubject($this->name())
                : Answer::empty($this->name(), 'لم أجد أي لاجئ يطابق «'.$subject.'» لأعرض حالة سكنه.');
        }

        if ($matches->count() > 1) {
            return Answer::make(
                $this->name(),
                'أكثر من شخص يطابق ما كتبت. اختر السجل المقصود:',
                $matches->map(fn (Refugee $refugee) => $this->refugeeItem($refugee))->all(),
            );
        }

        /** @var Refugee $refugee */
        $refugee = $matches->first();
        $lastMove = $refugee->residencyTransfers()->latest('transfer_date')->first();

        $figures = [
            ['label' => 'المخيم', 'value' => $refugee->currentCamp?->name ?? '—'],
            ['label' => 'الوحدة', 'value' => $refugee->currentShelter?->display_name ?? '—'],
            ['label' => 'حالة السكن', 'value' => Labels::get('housing_status', $refugee->housing_status)],
            ['label' => 'الوجود', 'value' => Labels::get('presence_status', $refugee->presence_status)],
        ];

        if ($lastMove?->transfer_date !== null) {
            $figures[] = ['label' => 'آخر انتقال', 'value' => $lastMove->transfer_date->format('Y-m-d')];
        }

        $text = $refugee->housing_status === 'assigned'
            ? $refugee->full_name.' مسكَّن في '.($refugee->currentShelter?->display_name ?? 'وحدة غير محددة')
                .($refugee->currentCamp !== null ? ' ب'.$this->campLabel($refugee->currentCamp) : '').'.'
            : $refugee->full_name.' غير مخصص له سكن حتى الآن.';

        return Answer::make(
            $this->name(),
            $text,
            [$this->refugeeItem($refugee)],
            $figures,
            $this->transferLink($refugee, $user),
        );
    }

    /**
     * @return list<array{label: string, url: string, icon: string}>
     */
    private function transferLink(Refugee $refugee, User $user): array
    {
        if (! $user->hasAnyRole(['admin', 'housing_officer'])) {
            return [];
        }

        return [[
            'label' => $refugee->housing_status === 'assigned' ? 'نقل إلى وحدة أخرى' : 'تخصيص سكن',
            'url' => route('housing.transfer.form', $refugee),
            'icon' => 'bed',
        ]];
    }

    public function examples(): array
    {
        return ['أين يسكن أحمد الحسن؟', 'ما حالة سكن الوثيقة DOC12345678؟'];
    }
}
