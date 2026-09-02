<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Models\Refugee;
use App\Models\Shelter;
use App\Models\User;
use App\Support\Labels;

/**
 * "من يسكن في الخيمة A-01؟" / "ما حالة الوحدة D01-001؟"
 */
class ShelterLookupIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const SUBJECTS = ['وحدة', 'وحدات', 'الوحدة', 'خيمة', 'الخيمة', 'كرفان', 'الكرفان', 'غرفة', 'الغرفة', 'مسكن', 'سكنية'];

    public function name(): string
    {
        return 'shelter_lookup';
    }

    public function group(): string
    {
        return 'housing';
    }

    public function score(AssistantQuery $query): ?int
    {
        if (! $query->hasAny(self::SUBJECTS)) {
            return null;
        }

        // A unit is named by its code. Without one the question is about the
        // stock of units in general, which ShelterAvailabilityIntent answers.
        if ($this->codeCandidates($query) === []) {
            return null;
        }

        // Above HousingStatusIntent on purpose: "من يسكن في الوحدة A-01" carries
        // the word "يسكن" too, and read as a person it becomes a search for a
        // refugee named "الوحدة A-01".
        return 5;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $matches = $this->sheltersIn($query, 5);

        if ($matches->isEmpty()) {
            return $this->unknownShelter($query);
        }

        if ($matches->count() > 1) {
            return Answer::make(
                $this->name(),
                $matches->count().' وحدات تطابق ما كتبته. اكتب الرمز كاملًا لتحديد واحدة، أو اختر من القائمة:',
                $matches->map(fn (Shelter $shelter) => $this->shelterItem($shelter, $user))->all(),
            );
        }

        /** @var Shelter $shelter */
        $shelter = $matches->first();

        $occupied = (int) $shelter->refugees_count;
        $capacity = (int) $shelter->capacity;
        $free = max(0, $capacity - $occupied);

        $residents = $shelter->refugees()
            ->where('status', 'active')
            ->with(['currentCamp', 'currentShelter', 'household'])
            ->orderBy('first_name')
            ->limit(8)
            ->get();

        $figures = [
            ['label' => 'المخيم', 'value' => $shelter->camp?->name ?? '—'],
            ['label' => 'النوع', 'value' => Labels::get('shelter_type', $shelter->type)],
            ['label' => 'الساكنون', 'value' => number_format($occupied)],
            ['label' => 'السعة', 'value' => number_format($capacity)],
            ['label' => 'أماكن شاغرة', 'value' => number_format($free)],
            ['label' => 'الحالة', 'value' => Labels::get('status', $shelter->status)],
        ];

        return Answer::make(
            $this->name(),
            $this->sentence($shelter, $occupied, $capacity, $free),
            $residents->map(fn (Refugee $refugee) => $this->refugeeItem($refugee))->all(),
            $figures,
            $user->hasAnyRole(['admin', 'housing_officer'])
                ? [['label' => 'فتح الوحدة', 'url' => route('shelters.edit', $shelter), 'icon' => 'home']]
                : [],
        );
    }

    /**
     * The three occupancy states read differently, so each gets its own
     * sentence rather than one phrasing with the numbers swapped in.
     */
    private function sentence(Shelter $shelter, int $occupied, int $capacity, int $free): string
    {
        $where = $shelter->camp !== null ? ' في '.$shelter->camp->name : '';

        if ($occupied === 0) {
            return $shelter->display_name.$where.' فارغة تمامًا، وسعتها '.number_format($capacity).' أشخاص.';
        }

        if ($free === 0) {
            return $shelter->display_name.$where.' ممتلئة: '.number_format($occupied)
                .' من أصل '.number_format($capacity).'، ولا مكان شاغر فيها.';
        }

        return $shelter->display_name.$where.' يسكنها '.number_format($occupied)
            .' من أصل '.number_format($capacity).'، ويتبقى '.number_format($free).' مكانًا شاغرًا.';
    }

    /**
     * @return array{title: string, subtitle: string, meta: string, url?: string}
     */
    private function shelterItem(Shelter $shelter, User $user): array
    {
        return array_filter([
            'title' => $shelter->display_name,
            'subtitle' => $shelter->camp?->name ?? '—',
            'meta' => $shelter->refugees_count.' من '.$shelter->capacity,
            'url' => $user->hasAnyRole(['admin', 'housing_officer']) ? route('shelters.edit', $shelter) : null,
        ]);
    }

    private function unknownShelter(AssistantQuery $query): Answer
    {
        $typed = $this->typedCode($query);

        return $this->unknownNamed(
            $this->name(),
            $typed !== ''
                ? 'لا توجد وحدة سكنية بالرمز «'.$typed.'» في النظام.'
                : 'لا توجد وحدة سكنية بهذا الرمز في النظام.',
            'الوحدات المسجّلة حاليًا:',
            Shelter::query()->with('camp')->orderBy('code')->limit(6)->get(),
            fn (Shelter $shelter): array => [
                'title' => $shelter->display_name,
                'subtitle' => $shelter->camp?->name ?? '—',
                'meta' => 'سعة '.$shelter->capacity,
            ],
        );
    }

    /**
     * The code as the user typed it. Candidates are read off the folded text,
     * which is lowercased, and quoting "z-99" back at someone who wrote "Z-99"
     * reads like a different code.
     */
    private function typedCode(AssistantQuery $query): string
    {
        $code = $this->codeCandidates($query)[0] ?? '';

        if ($code === '') {
            return '';
        }

        return preg_match('/'.preg_quote($code, '/').'/iu', $query->raw, $found) === 1 ? $found[0] : $code;
    }

    public function examples(): array
    {
        return ['من يسكن في الخيمة A-01؟', 'ما حالة الوحدة A-01؟'];
    }
}
