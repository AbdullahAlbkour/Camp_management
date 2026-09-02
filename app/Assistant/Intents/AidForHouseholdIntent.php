<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Models\AidDistribution;
use App\Models\Household;
use App\Models\User;

/**
 * "ما المساعدات التي استلمتها الأسرة HH-0012؟"
 */
class AidForHouseholdIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const AID = ['مساعدة', 'مساعدات', 'استلم', 'استلمت', 'تسلم', 'حصلت', 'حصل', 'توزيع', 'وزعت', 'موزعة', 'حصص', 'سلال'];

    /** @var list<string> */
    private const HOUSEHOLD = ['أسرة', 'الأسرة', 'أسر', 'الأسر', 'عائلة', 'العائلة', 'عائلات', 'رب الأسرة'];

    public function name(): string
    {
        return 'aid_for_household';
    }

    public function group(): string
    {
        return 'aid';
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::AID) || ! $query->hasAny(self::HOUSEHOLD)) {
            return null;
        }

        // Above AidForRefugeeIntent, which also claims "مساعدات" plus a name and
        // would otherwise read "الأسرة HH-0012" as a person to look up.
        return 5;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $triggers = array_merge(self::AID, self::HOUSEHOLD);
        $matches = $this->householdsIn($query, $triggers, 4);

        if ($matches->isEmpty()) {
            $subject = $query->subject($triggers);

            return $subject === '' && $this->codeCandidates($query) === []
                ? Answer::empty($this->name(), 'اكتب رمز الأسرة أو اسم رب الأسرة بعد السؤال، مثل: «ما مساعدات الأسرة HH-0012؟».')
                : Answer::empty($this->name(), 'لم أجد أسرة تطابق «'.($subject ?: implode(' ', $this->codeCandidates($query))).'» لأعرض مساعداتها.');
        }

        if ($matches->count() > 1) {
            return Answer::make(
                $this->name(),
                $matches->count().' أسر تطابق ما كتبته. اكتب رمز الأسرة كاملًا لتحديد واحدة، أو اختر من القائمة:',
                $matches->map(fn (Household $household): array => [
                    'title' => $household->household_code,
                    'subtitle' => 'رب الأسرة: '.($household->head?->full_name ?? '—'),
                    'meta' => $household->members_count.' فرد',
                    'url' => route('households.show', $household),
                ])->all(),
            );
        }

        /** @var Household $household */
        $household = $matches->first();

        // Aid reaches a household either as one delivery to the family or as
        // separate deliveries to its members; both are "what this family got".
        $memberIds = $household->members()->pluck('id');

        $distributions = AidDistribution::query()
            ->with(['aidType.organization', 'refugee'])
            ->where(fn ($inner) => $inner
                ->where('household_id', $household->id)
                ->orWhereIn('refugee_id', $memberIds))
            ->orderByDesc('distribution_date')
            ->limit(8)
            ->get();

        if ($distributions->isEmpty()) {
            return Answer::empty(
                $this->name(),
                'لا توجد مساعدات مسجّلة لأسرة '.$household->household_code.' ولا لأي من أفرادها.',
            );
        }

        $toFamily = $distributions->whereNull('refugee_id')->count();

        return Answer::make(
            $this->name(),
            'أسرة '.$household->household_code.' استلمت '.number_format($distributions->count())
                .' مساعدة مسجّلة، أحدثها '.($distributions->first()->aidType?->name ?? 'غير محددة').'.',
            $distributions->map(fn (AidDistribution $row): array => [
                'title' => $row->aidType?->name ?? 'مساعدة',
                'subtitle' => $row->refugee !== null
                    ? 'إلى '.$row->refugee->full_name
                    : 'إلى الأسرة كاملة'
                        .($row->aidType?->organization !== null ? ' • '.$row->aidType->organization->name : ''),
                'meta' => ($row->distribution_date?->format('Y-m-d') ?? '—').' • '.number_format((float) $row->quantity, 2),
            ])->all(),
            [
                ['label' => 'عدد المساعدات', 'value' => number_format($distributions->count())],
                ['label' => 'إلى الأسرة', 'value' => number_format($toFamily)],
                ['label' => 'إلى أفراد', 'value' => number_format($distributions->count() - $toFamily)],
                ['label' => 'عدد الأفراد', 'value' => number_format((int) $household->members_count)],
            ],
            [['label' => 'فتح ملف الأسرة', 'url' => route('households.show', $household), 'icon' => 'house']],
        );
    }

    public function examples(): array
    {
        return ['ما المساعدات التي استلمتها الأسرة HH-0012؟'];
    }
}
