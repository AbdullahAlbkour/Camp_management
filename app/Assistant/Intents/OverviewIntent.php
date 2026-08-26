<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use App\Models\User;

/**
 * "أعطني ملخصًا عن النظام" / "إحصائيات عامة"
 */
class OverviewIntent extends Intent
{
    /** @var list<string> */
    private const TRIGGERS = ['ملخص', 'نظرة عامة', 'إحصائيات عامة', 'إحصاءات عامة', 'وضع النظام', 'الوضع العام', 'تقرير سريع'];

    public function name(): string
    {
        return 'overview';
    }

    public function group(): string
    {
        return 'management';
    }

    public function score(AssistantQuery $query): ?int
    {
        return $query->hasAny(self::TRIGGERS) ? 3 : null;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $refugees = Refugee::query()->where('status', 'active')->count();
        $unhoused = Refugee::query()->where('status', 'active')->where('housing_status', 'unassigned')->count();
        $households = Household::query()->count();
        $camps = Camp::query()->where('status', 'active')->count();
        $shelters = Shelter::query()->where('status', 'active')->count();

        if ($refugees === 0 && $households === 0 && $camps === 0) {
            return Answer::empty($this->name(), 'النظام فارغ حتى الآن — لا توجد مخيمات أو سكان مسجّلون.');
        }

        return Answer::make(
            $this->name(),
            'يضم النظام '.number_format($refugees).' لاجئًا فعّالًا في '.number_format($camps)
                .' مخيمًا، منهم '.number_format($unhoused).' بلا سكن.',
            [],
            [
                ['label' => 'السكان الفعّالون', 'value' => number_format($refugees)],
                ['label' => 'الأسر', 'value' => number_format($households)],
                ['label' => 'المخيمات الفعّالة', 'value' => number_format($camps)],
                ['label' => 'الوحدات الفعّالة', 'value' => number_format($shelters)],
                ['label' => 'بلا سكن', 'value' => number_format($unhoused)],
            ],
            [['label' => 'فتح لوحة التحكم', 'url' => route('dashboard'), 'icon' => 'layout-dashboard']],
            ['كم وحدة سكنية فارغة؟', 'كم مساعدة وُزّعت هذا الشهر؟'],
        );
    }

    public function examples(): array
    {
        return ['أعطني ملخصًا عن وضع النظام'];
    }
}
