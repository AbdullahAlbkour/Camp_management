<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Assistant\TimeWindow;
use App\Models\AidDistribution;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "كم مساعدة وُزّعت هذا الشهر؟" / "مساعدات مخيم الزعتري اليوم"
 */
class AidSummaryIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const SUBJECTS = ['مساعدة', 'مساعدات', 'توزيع', 'موزعة', 'وزعت', 'حصص', 'سلال', 'إغاثة'];

    public function name(): string
    {
        return 'aid_summary';
    }

    public function group(): string
    {
        return 'aid';
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::SUBJECTS)) {
            return null;
        }

        // A person named in the question means "what did they receive", which
        // AidForRefugeeIntent answers; this one is the aggregate.
        if ($query->codes() !== []) {
            return null;
        }

        $signals = 1;

        if (TimeWindow::in($query) !== null) {
            $signals++;
        }

        if ($this->campReference($query)->isResolved()) {
            $signals++;
        }

        return $signals + 1;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $reference = $this->campReference($query);

        if ($reference->isUnknown()) {
            return $this->unknownCamp($this->name(), $reference);
        }

        $camp = $reference->camp;
        [$from, $to, $label] = TimeWindow::range(TimeWindow::in($query));

        $base = AidDistribution::query()
            ->whereBetween('distribution_date', [$from, $to])
            ->when($camp !== null, fn ($q) => $q->where('camp_id', $camp->id));

        $operations = (clone $base)->count();
        $where = $camp !== null ? ' في '.$reference->label() : '';

        if ($operations === 0) {
            return Answer::empty(
                $this->name(),
                'لم تُسجَّل أي عملية توزيع'.$where.' '.$label.'.',
            );
        }

        $quantity = (float) (clone $base)->sum('quantity');

        // A distribution targets a refugee or a household, never both, so the
        // two distinct counts add up without overlapping.
        $beneficiaries = (clone $base)->whereNotNull('refugee_id')->distinct()->count('refugee_id')
            + (clone $base)->whereNotNull('household_id')->distinct()->count('household_id');

        $byType = (clone $base)
            ->join('aid_types', 'aid_types.id', '=', 'aid_distributions.aid_type_id')
            ->select('aid_types.name', 'aid_types.unit', DB::raw('count(*) as total'), DB::raw('sum(quantity) as amount'))
            ->groupBy('aid_types.name', 'aid_types.unit')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return Answer::make(
            $this->name(),
            'وُزّعت '.number_format($operations).' مساعدة'.$where.' '.$label
                .' على '.number_format($beneficiaries).' مستفيدًا.',
            $byType->map(fn ($row): array => [
                'title' => (string) $row->name,
                'subtitle' => 'إجمالي الكمية '.number_format((float) $row->amount, 2).' '.($row->unit ?: 'وحدة'),
                'meta' => number_format((int) $row->total).' عملية',
            ])->all(),
            [
                ['label' => 'عمليات التوزيع', 'value' => number_format($operations)],
                ['label' => 'المستفيدون', 'value' => number_format($beneficiaries)],
                ['label' => 'إجمالي الكميات', 'value' => number_format($quantity, 2)],
            ],
            // A manager sees aid figures on the dashboard but cannot open the
            // distribution screens, so the link is offered only where it works.
            $user->hasAnyRole(['admin', 'aid_officer'])
                ? [['label' => 'فتح سجل التوزيع', 'url' => route('aid.distributions'), 'icon' => 'package-check']]
                : [],
        );
    }

    public function examples(): array
    {
        return ['كم مساعدة وُزّعت هذا الشهر؟', 'مساعدات {camp} هذا الأسبوع'];
    }
}
