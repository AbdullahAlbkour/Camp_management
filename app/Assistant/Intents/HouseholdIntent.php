<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\User;
use App\Support\ArabicText;
use App\Support\RoleScope;

/**
 * "أفراد أسرة HH-0012" / "كم عدد الأسر؟"
 */
class HouseholdIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const TRIGGERS = ['أسرة', 'أسر', 'الأسرة', 'الأسر', 'عائلة', 'عائلات', 'رب الأسرة'];

    /** @var list<string> */
    private const COUNTING = ['كم', 'عدد', 'مجموع', 'إجمالي', 'إحصائية'];

    public function name(): string
    {
        return 'household';
    }

    public function group(): string
    {
        return RoleScope::LOOKUP;
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::TRIGGERS)) {
            return null;
        }

        return 3;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $subject = $query->subject(self::TRIGGERS);
        $codes = $query->codes();

        // A counting question with nothing to identify one household is asking
        // about the whole register.
        if ($codes === [] && ArabicText::isTooShort($subject, 2) && $query->hasAny(self::COUNTING)) {
            return $this->overview();
        }

        $matches = Household::query()
            ->with('head')
            ->withCount('members')
            ->where(function ($inner) use ($codes, $subject): void {
                foreach ($codes as $code) {
                    $inner->orWhereRaw('LOWER(household_code) like ?', ['%'.$code.'%']);
                }

                if (! ArabicText::isTooShort($subject, 2)) {
                    $inner->orWhereRaw('LOWER(household_code) like ?', ['%'.$subject.'%'])
                        ->orWhereHas('head', fn ($head) => $head->where('search_text', 'like', '%'.$subject.'%'));
                }
            })
            ->limit(5)
            ->get();

        if ($matches->isEmpty()) {
            return $codes === [] && ArabicText::isTooShort($subject, 2)
                ? $this->overview()
                : Answer::empty($this->name(), 'لم أجد أسرة تطابق «'.($subject ?: implode(' ', $codes)).'».');
        }

        if ($matches->count() > 1) {
            return Answer::make(
                $this->name(),
                'وجدت '.$matches->count().' أسر مطابقة:',
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

        $members = $household->members()
            ->with(['currentCamp', 'currentShelter'])
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$household->head_of_household_id ?? 0])
            ->limit(8)
            ->get();

        $housed = $members->where('housing_status', 'assigned')->count();

        return Answer::make(
            $this->name(),
            'أسرة '.$household->household_code.' تضم '.$household->members_count.' فردًا،'
                .' رب الأسرة '.($household->head?->full_name ?? 'غير محدد').'.',
            $members->map(fn (Refugee $refugee) => $this->refugeeItem($refugee))->all(),
            [
                ['label' => 'عدد الأفراد', 'value' => (string) $household->members_count],
                ['label' => 'مسكَّنون', 'value' => (string) $housed],
                ['label' => 'بلا سكن', 'value' => (string) max(0, $household->members_count - $housed)],
            ],
            [['label' => 'فتح ملف الأسرة', 'url' => route('households.show', $household), 'icon' => 'house']],
        );
    }

    private function overview(): Answer
    {
        $total = Household::query()->count();

        if ($total === 0) {
            return Answer::empty($this->name(), 'لا توجد أسر مسجّلة في النظام حتى الآن.');
        }

        $withoutHead = Household::query()->whereNull('head_of_household_id')->count();
        $members = Refugee::query()->whereNotNull('household_id')->where('status', 'active')->count();

        return Answer::make(
            $this->name(),
            'يوجد '.number_format($total).' أسرة مسجّلة، تضم '.number_format($members).' فردًا فعّالًا.',
            [],
            [
                ['label' => 'عدد الأسر', 'value' => number_format($total)],
                ['label' => 'أفراد مرتبطون بأسرة', 'value' => number_format($members)],
                ['label' => 'بلا رب أسرة', 'value' => number_format($withoutHead)],
                ['label' => 'متوسط الحجم', 'value' => number_format($members / $total, 1)],
            ],
            [['label' => 'فتح قائمة الأسر', 'url' => route('households.index'), 'icon' => 'house']],
        );
    }

    public function examples(): array
    {
        return ['أفراد أسرة HH-0012', 'كم عدد الأسر المسجلة؟'];
    }
}
