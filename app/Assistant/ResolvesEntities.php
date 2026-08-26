<?php

namespace App\Assistant;

use App\Models\Camp;
use App\Models\Refugee;
use App\Support\ArabicText;
use App\Support\Labels;
use App\Support\SearchExpression;
use Illuminate\Support\Collection;

/**
 * Turning the nouns in a question into rows from the database.
 */
trait ResolvesEntities
{
    /**
     * Words that follow "مخيم" without naming one, so "كم عدد السكان في المخيم"
     * stays a question about the whole system rather than one about a camp
     * called "النظام".
     *
     * @var list<string>
     */
    private const NOT_A_CAMP_NAME = [
        'النظام', 'الجميع', 'الكل', 'كلها', 'جميعا', 'عموما', 'لدينا', 'المسجلة',
        'اليوم', 'امس', 'الشهر', 'الاسبوع', 'السنه', 'العام', 'الحالي', 'الماضي',
    ];

    /**
     * What the question said about a camp: nothing, a camp that exists, or a
     * camp that does not.
     *
     * The third case is the one that matters. Treating it as "nothing" is what
     * made the assistant answer a question about مخيم الزعتري with the totals of
     * every other camp, phrased as though it had understood.
     */
    protected function campReference(AssistantQuery $query): CampReference
    {
        $camp = $this->campIn($query);

        if ($camp !== null) {
            return CampReference::of($camp);
        }

        $keyword = $this->campKeywordIndex($query);

        if ($keyword === null) {
            return CampReference::none();
        }

        $name = $this->nameAfter($query, $keyword);

        return $name === null ? CampReference::none() : CampReference::unknown($name);
    }

    /**
     * The camp named in the question, if one is.
     *
     * Camps are matched by folding their stored names and looking for them in
     * the folded question, so "مخيم الزعتري" and "زعتري" both land. The longest
     * name wins, so "الزعتري الشمالي" is not swallowed by "الزعتري".
     */
    protected function campIn(AssistantQuery $query): ?Camp
    {
        if ($query->text === '') {
            return null;
        }

        return Camp::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Camp $camp) => [$camp, ArabicText::normalize($camp->name)])
            ->filter(fn (array $pair) => $pair[1] !== '' && str_contains($query->text, $pair[1]))
            ->sortByDesc(fn (array $pair) => mb_strlen($pair[1]))
            ->map(fn (array $pair) => $pair[0])
            ->first();
    }

    /**
     * The tokens that belong to a camp mention, so an intent can drop them
     * before reading what is left as a name.
     *
     * Without this, "كم عدد الأسر في مخيم السلام" looks for a household called
     * "مخيم السلام" and reports it missing.
     *
     * @return list<string>
     */
    protected function campWords(AssistantQuery $query): array
    {
        $keyword = $this->campKeywordIndex($query);

        if ($keyword === null) {
            return [];
        }

        $words = [$query->words[$keyword]];
        $reference = $this->campReference($query);

        $name = $reference->isResolved()
            ? ArabicText::normalize($reference->camp->name)
            : ArabicText::normalize((string) $reference->unknownName);

        foreach (preg_split('/\s+/u', $name) ?: [] as $part) {
            if ($part !== '') {
                $words[] = $part;
            }
        }

        return $words;
    }

    /**
     * Where the word "مخيم" appears, or null when the question never says it.
     *
     * The plural counts as no reference at all: "كم عدد السكان في المخيمات" asks
     * about all of them, so there is no name to fail to resolve.
     */
    private function campKeywordIndex(AssistantQuery $query): ?int
    {
        foreach ($query->words as $index => $word) {
            $bare = preg_replace('/^(و|ف|ب|ل|ك)?(ال)?/u', '', $word) ?? $word;

            if ($bare === 'مخيمات' || $word === 'المخيمات') {
                return null;
            }

            if (str_starts_with($bare, 'مخيم')) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The name written after "مخيم", or null when nothing name-like follows.
     *
     * At most two words are taken, and the run stops at the first word that is
     * not part of a name — otherwise "مخيم النور اليوم" would report a camp
     * called "النور اليوم" as missing.
     */
    private function nameAfter(AssistantQuery $query, int $keyword): ?string
    {
        $parts = [];

        for ($i = $keyword + 1; $i < count($query->words) && count($parts) < 2; $i++) {
            $word = $query->words[$i];

            if (in_array($word, self::NOT_A_CAMP_NAME, true) || preg_match('/\d/u', $word) === 1) {
                break;
            }

            // Quoted back as typed, so the user sees their own spelling rather
            // than the folded form the matching ran on.
            $parts[] = $query->rawWords[$i] ?? $word;
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * Refugees matching the person referred to in the question.
     *
     * Identifiers are tried before names: someone who typed a document number
     * means that exact person, and a partial name match would bury them.
     *
     * @param  list<string>  $triggers
     * @return Collection<int, Refugee>
     */
    protected function refugeesIn(AssistantQuery $query, array $triggers, int $limit = 5): Collection
    {
        $base = Refugee::query()->with(['currentCamp', 'currentShelter', 'household']);

        foreach ($query->codes() as $code) {
            // Folding lowercases the term, so the comparison has to lowercase the
            // column too — "DOC55443" as stored would otherwise never equal
            // "doc55443" as typed.
            $exact = (clone $base)
                ->where(fn ($inner) => $inner
                    ->whereRaw(SearchExpression::lower('document_number').' = ?', [$code])
                    ->orWhereRaw(SearchExpression::lower('phone').' = ?', [$code]))
                ->limit($limit)
                ->get();

            if ($exact->isNotEmpty()) {
                return $exact;
            }
        }

        // A badge reads REF-000123; the folded form leaves the digits behind, so
        // an id lookup covers both "REF-000123" and a bare record number.
        foreach ($query->numbers() as $number) {
            $byId = (clone $base)->whereKey($number)->get();

            if ($byId->isNotEmpty()) {
                return $byId;
            }
        }

        $name = $query->subject($triggers);

        if (ArabicText::isTooShort($name)) {
            return new Collection;
        }

        return $base->where('search_text', 'like', '%'.$name.'%')->limit($limit)->get();
    }

    /**
     * @return array{title: string, subtitle: string, meta: string, url: string}
     */
    protected function refugeeItem(Refugee $refugee): array
    {
        $place = $refugee->currentShelter?->display_name
            ?? ($refugee->housing_status === 'unassigned' ? 'بدون سكن' : '—');

        return [
            'title' => $refugee->full_name,
            'subtitle' => ($refugee->currentCamp?->name ?? 'بدون مخيم').' • '.$place,
            'meta' => $refugee->document_number ?: $refugee->badge_code,
            'url' => route('refugees.show', $refugee),
        ];
    }

    /**
     * The answer for a camp the system does not have.
     *
     * It lists the camps that do exist, because the usual cause is a spelling
     * the register does not share, and the real names answer that in one step.
     */
    protected function unknownCamp(string $intent, CampReference $reference): Answer
    {
        $camps = Camp::query()->orderBy('name')->limit(8)->get();

        $named = $reference->unknownName !== null && $reference->unknownName !== ''
            ? 'لا يوجد مخيم باسم «'.$reference->unknownName.'» في النظام.'
            : 'لا يوجد مخيم بهذا الاسم في النظام.';

        if ($camps->isEmpty()) {
            return Answer::empty($intent, $named.' لا توجد مخيمات مسجّلة أصلًا.');
        }

        return Answer::empty(
            $intent,
            $named.' المخيمات المسجّلة حاليًا:',
            $camps->map(fn (Camp $camp): array => [
                'title' => $camp->name,
                'subtitle' => $camp->location ?: '—',
                'meta' => Labels::get('status', $camp->status),
            ])->all(),
        );
    }

    /**
     * Wording for a person the question did not pin down.
     */
    protected function noSubject(string $intent): Answer
    {
        return Answer::empty(
            $intent,
            'لم أتعرف على اسم أو رقم في سؤالك. اكتب الاسم أو رقم الوثيقة بعد السؤال، مثل: «أين يسكن أحمد الحسن؟».',
        );
    }

    protected function statusLabel(Refugee $refugee): string
    {
        return Labels::get('refugee_status', $refugee->status);
    }
}
