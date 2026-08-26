<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Assistant\TimeWindow;
use App\Models\AidDistribution;
use App\Models\Refugee;
use App\Models\User;

/**
 * "ماذا استلم أحمد من مساعدات؟" / "مساعدات الوثيقة DOC12345678"
 */
class AidForRefugeeIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const TRIGGERS = ['مساعدة', 'مساعدات', 'استلم', 'تسلم', 'استلمت', 'حصل', 'أخذ', 'توزيع', 'وزعت', 'وزع', 'موزعة'];

    /** @var list<string> */
    private const AGGREGATE = ['كم', 'عدد', 'مجموع', 'إجمالي', 'إحصائية', 'إحصائيات', 'نسبة'];

    public function name(): string
    {
        return 'aid_for_refugee';
    }

    public function group(): string
    {
        return 'aid';
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::TRIGGERS)) {
            return null;
        }

        // Counting words and named periods are the marks of an aggregate
        // question. Without standing down here, "كم مساعدة وُزّعت هذا الشهر"
        // reads its leftover words as a person's name and answers the wrong
        // question with a confident empty result.
        if ($query->hasAny(self::AGGREGATE) || TimeWindow::in($query) !== null) {
            return null;
        }

        // Naming a person is what distinguishes this from the aggregate summary.
        return $query->codes() !== [] || $query->subject(self::TRIGGERS) !== '' ? 4 : null;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $matches = $this->refugeesIn($query, self::TRIGGERS, 4);

        if ($matches->isEmpty()) {
            $subject = $query->subject(self::TRIGGERS);

            return $subject === ''
                ? $this->noSubject($this->name())
                : Answer::empty($this->name(), 'لم أجد أي لاجئ يطابق «'.$subject.'» لأعرض مساعداته.');
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

        // Aid reaches a person directly or through their household, and both
        // count as "what they received".
        $distributions = AidDistribution::query()
            ->with('aidType')
            ->where(fn ($inner) => $inner
                ->where('refugee_id', $refugee->id)
                ->when($refugee->household_id !== null, fn ($q) => $q->orWhere('household_id', $refugee->household_id)))
            ->orderByDesc('distribution_date')
            ->limit(6)
            ->get();

        if ($distributions->isEmpty()) {
            return Answer::empty(
                $this->name(),
                'لا توجد مساعدات مسجّلة باسم '.$refugee->full_name.' ولا باسم أسرته.',
            );
        }

        return Answer::make(
            $this->name(),
            $refugee->full_name.' استلم '.$distributions->count().' مساعدة مسجّلة، أحدثها '
                .($distributions->first()->aidType?->name ?? 'غير محددة').'.',
            $distributions->map(fn (AidDistribution $row): array => [
                'title' => $row->aidType?->name ?? 'مساعدة',
                'subtitle' => $row->household_id !== null && $row->refugee_id === null ? 'عبر الأسرة' : 'مباشرة',
                'meta' => ($row->distribution_date?->format('Y-m-d') ?? '—').' • '.number_format((float) $row->quantity, 2),
            ])->all(),
            links: [['label' => 'ملف اللاجئ', 'url' => route('refugees.show', $refugee), 'icon' => 'user-round']],
        );
    }

    public function examples(): array
    {
        return ['ماذا استلم أحمد الحسن من مساعدات؟'];
    }
}
