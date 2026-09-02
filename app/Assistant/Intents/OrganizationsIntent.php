<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Models\AidType;
use App\Models\Organization;
use App\Models\User;
use App\Support\Labels;

/**
 * "كم عدد الجهات الداعمة؟" / "ما هي المنظمات المسجلة؟"
 */
class OrganizationsIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const SUBJECTS = ['جهة', 'جهات', 'الجهات', 'منظمة', 'منظمات', 'المنظمات', 'مانحة', 'المانحة', 'داعمة', 'الداعمة', 'شركاء', 'الشركاء'];

    /** Aid words push the question to the donor-specific intents instead. */
    private const DISTRIBUTION = ['مساعدة', 'مساعدات', 'توزيع', 'وزعت', 'موزعة', 'قدمت', 'المقدمة'];

    public function name(): string
    {
        return 'organizations';
    }

    public function group(): string
    {
        return 'aid';
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::SUBJECTS) || $query->hasAny(self::DISTRIBUTION)) {
            return null;
        }

        return 3;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $total = Organization::query()->count();

        if ($total === 0) {
            return Answer::empty($this->name(), 'لا توجد جهات داعمة مسجّلة في النظام حتى الآن.');
        }

        $active = Organization::query()->where('status', 'active')->count();

        $organizations = Organization::query()
            ->withCount('aidTypes')
            ->orderBy('name')
            ->limit(8)
            ->get();

        return Answer::make(
            $this->name(),
            'يوجد '.number_format($total).' جهة داعمة مسجّلة، منها '.number_format($active).' فعّالة.',
            $organizations->map(fn (Organization $organization): array => [
                'title' => $organization->name,
                'subtitle' => $organization->contact_name
                    ? 'مسؤول الاتصال: '.$organization->contact_name
                    : 'بدون مسؤول اتصال',
                'meta' => $organization->aid_types_count.' نوع مساعدة • '.Labels::get('status', $organization->status),
            ])->all(),
            [
                ['label' => 'إجمالي الجهات', 'value' => number_format($total)],
                ['label' => 'فعّالة', 'value' => number_format($active)],
                ['label' => 'غير فعّالة', 'value' => number_format($total - $active)],
                ['label' => 'أنواع المساعدات', 'value' => number_format(AidType::query()->count())],
            ],
            $user->hasAnyRole(['admin', 'aid_officer'])
                ? [['label' => 'فتح الجهات الداعمة', 'url' => route('aid.organizations'), 'icon' => 'package']]
                : [],
            ['ما المساعدات المقدمة من '.($organizations->first()?->name ?? 'الهلال المحلي').'؟'],
        );
    }

    public function examples(): array
    {
        return ['كم عدد الجهات الداعمة المسجلة؟'];
    }
}
