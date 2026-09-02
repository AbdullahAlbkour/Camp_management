<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Assistant\TimeWindow;
use App\Models\AidDistribution;
use App\Models\AidType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "ما المساعدات المقدمة من اليونيسف؟" / "كم وزّع الهلال المحلي هذا الشهر؟"
 */
class AidByOrganizationIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const AID = ['مساعدة', 'مساعدات', 'توزيع', 'وزعت', 'وزع', 'موزعة', 'قدمت', 'المقدمة', 'حصص', 'سلال', 'إغاثة', 'تبرع', 'تبرعات'];

    /** @var list<string> */
    private const ORG_WORDS = ['جهة', 'الجهة', 'جهات', 'الجهات', 'منظمة', 'المنظمة', 'منظمات', 'مانحة', 'داعمة', 'الداعمة', 'المانحة'];

    public function name(): string
    {
        return 'aid_by_organization';
    }

    public function group(): string
    {
        return 'aid';
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::AID)) {
            return null;
        }

        $organization = $this->organizationIn($query);

        if ($organization === null && ! $query->hasAny(self::ORG_WORDS)) {
            return null;
        }

        // Above AidSummaryIntent, which claims the same aid words but answers
        // for the whole camp rather than for one donor.
        return $organization !== null ? 5 : 4;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $organization = $this->organizationIn($query);

        if ($organization === null) {
            return $this->unknownOrganization($query);
        }

        [$from, $to, $label] = TimeWindow::range(TimeWindow::in($query));
        $window = TimeWindow::in($query);

        $typeIds = AidType::query()->where('organization_id', $organization->id)->pluck('id');

        if ($typeIds->isEmpty()) {
            return Answer::empty(
                $this->name(),
                'لا توجد أنواع مساعدات مسجّلة باسم '.$organization->name.' حتى الآن.',
            );
        }

        $base = AidDistribution::query()
            ->whereIn('aid_type_id', $typeIds)
            // A donor question without a stated period means "so far", not
            // "this month": narrowing it silently would hide most of the record.
            ->when($window !== null, fn ($q) => $q->whereBetween('distribution_date', [$from, $to]));

        $operations = (clone $base)->count();
        $when = $window !== null ? ' '.$label : '';

        if ($operations === 0) {
            return Answer::empty(
                $this->name(),
                'لم تُسجَّل أي عملية توزيع من '.$organization->name.$when.'.',
            );
        }

        $beneficiaries = (clone $base)->distinct()->count('refugee_id');

        return Answer::make(
            $this->name(),
            $organization->name.' قدّمت '.number_format($operations).' عملية توزيع'.$when.'.',
            $this->perType($typeIds, $from, $to, $window),
            [
                ['label' => 'عمليات التوزيع', 'value' => number_format($operations)],
                ['label' => 'أنواع المساعدات', 'value' => number_format($typeIds->count())],
                ['label' => 'مستفيدون أفراد', 'value' => number_format($beneficiaries)],
                ['label' => 'الفترة', 'value' => $window !== null ? $label : 'منذ البداية'],
            ],
            $user->hasAnyRole(['admin', 'aid_officer'])
                ? [['label' => 'فتح الجهات الداعمة', 'url' => route('aid.organizations'), 'icon' => 'package']]
                : [],
        );
    }

    /**
     * @param  Collection<int, int>  $typeIds
     * @return list<array{title: string, subtitle: string, meta: string}>
     */
    private function perType($typeIds, $from, $to, ?string $window): array
    {
        return AidDistribution::query()
            ->whereIn('aid_type_id', $typeIds)
            ->when($window !== null, fn ($q) => $q->whereBetween('distribution_date', [$from, $to]))
            ->selectRaw('aid_type_id, count(*) as operations, sum(quantity) as total')
            ->groupBy('aid_type_id')
            ->orderByDesc('operations')
            ->limit(6)
            ->with('aidType')
            ->get()
            ->map(fn (AidDistribution $row): array => [
                'title' => $row->aidType?->name ?? 'مساعدة',
                'subtitle' => number_format((int) $row->operations).' عملية توزيع',
                'meta' => number_format((float) $row->total, 2).' '.($row->aidType?->unit ?? ''),
            ])
            ->values()
            ->all();
    }

    private function unknownOrganization(AssistantQuery $query): Answer
    {
        $named = $query->subject(array_merge(self::AID, self::ORG_WORDS));

        return $this->unknownNamed(
            $this->name(),
            $named !== ''
                ? 'لا توجد جهة داعمة باسم «'.$named.'» في النظام.'
                : 'لا توجد جهة داعمة بهذا الاسم في النظام.',
            'الجهات المسجّلة حاليًا:',
            Organization::query()->orderBy('name')->limit(8)->get(),
            fn (Organization $organization): array => [
                'title' => $organization->name,
                'subtitle' => $organization->contact_name ?: '—',
                'meta' => $organization->phone ?: '—',
            ],
        );
    }

    public function examples(): array
    {
        return ['ما المساعدات المقدمة من الهلال المحلي؟'];
    }
}
