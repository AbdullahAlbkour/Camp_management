<?php

namespace App\Assistant;

use App\Models\Camp;
use App\Models\Checkpoint;
use App\Models\Household;
use App\Models\Organization;
use App\Models\Refugee;
use App\Models\Shelter;
use App\Support\ArabicText;
use App\Support\Labels;
use App\Support\SearchExpression;
use Illuminate\Database\Eloquent\Model;
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

        // codeCandidates() first: it keeps a hyphenated code whole, and the
        // question's own tokens have already been split on the hyphen. Without
        // it "POP-000095" arrives here as the bare "000095", never equals the
        // stored "POP-000095", and falls through to the record-number lookup
        // below — which used to answer with whichever refugee happened to hold
        // id 95, under the document number that was asked for.
        $codes = array_values(array_unique(array_merge($this->codeCandidates($query), $query->codes())));

        foreach ($codes as $code) {
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

        foreach ($this->recordNumbersIn($query) as $number) {
            $byId = (clone $base)->whereKey($number)->get();

            if ($byId->isNotEmpty()) {
                return $byId;
            }
        }

        $name = $query->subject($triggers);

        if (ArabicText::isTooShort($name)) {
            return new Collection;
        }

        // Arabic glues the preposition onto the name it introduces, so "آخر حركة
        // لكرم" leaves "لكرم" where the register holds "كرم". Both spellings are
        // tried: a wrong strip simply matches nothing extra.
        $spellings = array_values(array_unique(array_filter([$name, $this->withoutAttachedPreposition($name)])));

        return $base
            ->where(function ($inner) use ($spellings): void {
                foreach ($spellings as $spelling) {
                    $inner->orWhere('search_text', 'like', '%'.$spelling.'%');
                }
            })
            ->limit($limit)
            ->get();
    }

    /**
     * The numbers in a question that actually name a record.
     *
     * A badge reads REF-000123, and a number typed on its own is a record
     * number; both point at a row id. Digits taken out of any other code do
     * not: "POP-000095" is a document number, and a record-id lookup on 95
     * returns a different person's file under the number that was asked for —
     * a wrong answer given with the same confidence as a right one, which is
     * the one failure this assistant is built not to produce. So an identifier
     * that carries letters is either matched as the code it is, or not matched
     * at all.
     *
     * @return list<int>
     */
    private function recordNumbersIn(AssistantQuery $query): array
    {
        $numbers = [];

        foreach ($this->codeCandidates($query) as $code) {
            if (preg_match('/^(?:ref-)?0*(\d+)$/u', $code, $matches) === 1) {
                $numbers[] = (int) $matches[1];
            }
        }

        // codeCandidates() ignores single characters, so a one-digit record
        // number is picked up from the tokens instead.
        foreach ($query->words as $word) {
            if (preg_match('/^\d$/u', $word) === 1) {
                $numbers[] = (int) $word;
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * The name with a leading و/ب/ل/ك dropped from its first word, or an empty
     * string when dropping it would leave too little to search on.
     */
    private function withoutAttachedPreposition(string $name): string
    {
        $words = preg_split('/\s+/u', $name) ?: [];

        if ($words === []) {
            return '';
        }

        $first = preg_replace('/^(و|ب|ل|ك|ف)/u', '', $words[0]) ?? $words[0];

        if ($first === $words[0] || mb_strlen($first) < 2) {
            return '';
        }

        $words[0] = $first;

        return implode(' ', $words);
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
     * Several people match the name, so the question cannot be answered about
     * one of them.
     *
     * The reply says how to narrow it rather than only that it is ambiguous:
     * in a camp of thousands a given name matches dozens, and "اختر السجل" is
     * no help to someone who typed the only name they had. The matches are
     * still listed so a click can settle it in one step.
     *
     * @param  Collection<int, Refugee>  $matches
     */
    protected function tooManyPeople(string $intent, Collection $matches, string $subject = ''): Answer
    {
        $named = $subject !== '' ? '«'.$subject.'»' : 'هذا الاسم';

        return Answer::make(
            $intent,
            $matches->count().' أشخاص يطابقون '.$named.'. اكتب الاسم الثلاثي كاملًا أو رقم الوثيقة'
                .' لتحديد الشخص، أو اختر من القائمة:',
            $matches->map(fn (Refugee $refugee) => $this->refugeeItem($refugee))->values()->all(),
            followUps: ['أين يسكن '.($matches->first()?->full_name ?? '').'؟'],
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

    /**
     * Code-like tokens read straight off the folded question.
     *
     * `AssistantQuery::codes()` reads the tokenised form, and tokenising splits
     * on the hyphen — so a unit code like "A-01" arrives as the fragments "a"
     * and "01" and never matches the column it names. Codes are therefore
     * matched against the folded text, where the hyphen is still intact.
     *
     * @return list<string>
     */
    protected function codeCandidates(AssistantQuery $query): array
    {
        preg_match_all('/[a-z0-9]+(?:-[a-z0-9]+)*/u', $query->text, $matches);

        $codes = array_filter(
            $matches[0],
            static fn (string $token) => preg_match('/\d/u', $token) === 1 && mb_strlen($token) >= 2
        );

        // Longest first: "hh-demo-0001" is a better identifier than the "0001"
        // sitting inside it, and trying it first avoids a needless wide scan.
        usort($codes, static fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_values(array_unique($codes));
    }

    /**
     * The shelters a question points at by code.
     *
     * Exact matches are returned alone when there are any: someone who typed a
     * full unit code means that unit, and a partial scan would bury it under
     * every code that merely contains the same digits.
     *
     * @return Collection<int, Shelter>
     */
    protected function sheltersIn(AssistantQuery $query, int $limit = 5): Collection
    {
        $candidates = $this->codeCandidates($query);

        if ($candidates === []) {
            return new Collection;
        }

        $base = fn () => Shelter::query()
            ->with('camp')
            ->withCount(['refugees' => fn ($inner) => $inner->where('status', 'active')]);

        foreach (['=', 'like'] as $operator) {
            $matches = $base()
                ->where(function ($inner) use ($candidates, $operator): void {
                    foreach ($candidates as $code) {
                        $inner->orWhereRaw(
                            SearchExpression::lower('code').' '.$operator.' ?',
                            [$operator === 'like' ? '%'.$code.'%' : $code]
                        );
                    }
                })
                ->limit($limit)
                ->get();

            if ($matches->isNotEmpty()) {
                return $matches;
            }
        }

        return new Collection;
    }

    /**
     * The households a question points at, by code or by the head's name.
     *
     * @param  list<string>  $triggers
     * @return Collection<int, Household>
     */
    protected function householdsIn(AssistantQuery $query, array $triggers, int $limit = 5): Collection
    {
        $codes = array_merge($this->codeCandidates($query), $query->codes());
        $subject = $query->subject(array_merge($triggers, $this->campWords($query)));
        $named = ! ArabicText::isTooShort($subject, 2);

        if ($codes === [] && ! $named) {
            return new Collection;
        }

        return Household::query()
            ->with('head')
            ->withCount('members')
            ->where(function ($inner) use ($codes, $subject, $named): void {
                foreach ($codes as $code) {
                    $inner->orWhereRaw(SearchExpression::lower('household_code').' like ?', ['%'.$code.'%']);
                }

                if ($named) {
                    $inner->orWhereRaw(SearchExpression::lower('household_code').' like ?', ['%'.$subject.'%'])
                        ->orWhereHas('head', fn ($head) => $head->where('search_text', 'like', '%'.$subject.'%'));
                }
            })
            ->limit($limit)
            ->get();
    }

    /**
     * The checkpoint named in the question, matched the way camps are: the
     * stored name folded and looked for inside the folded question, longest
     * name first so "البوابة الشمالية" is not swallowed by "البوابة".
     */
    protected function checkpointIn(AssistantQuery $query): ?Checkpoint
    {
        return $this->namedRecord($query, Checkpoint::query()->with('camp')->orderBy('name')->get());
    }

    protected function organizationIn(AssistantQuery $query): ?Organization
    {
        return $this->namedRecord($query, Organization::query()->orderBy('name')->get());
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $records
     * @return TModel|null
     */
    private function namedRecord(AssistantQuery $query, Collection $records)
    {
        if ($query->text === '') {
            return null;
        }

        return $records
            ->map(fn ($record) => [$record, ArabicText::normalize($record->name)])
            ->filter(fn (array $pair) => $pair[1] !== '' && str_contains($query->text, $pair[1]))
            ->sortByDesc(fn (array $pair) => mb_strlen($pair[1]))
            ->map(fn (array $pair) => $pair[0])
            ->first();
    }

    /**
     * A named thing the register does not have.
     *
     * Same shape as `unknownCamp()`: say plainly that it is missing, then list
     * what does exist, because the usual cause is a spelling the register does
     * not share and the real names settle it in one step.
     *
     * The caller supplies both sentences rather than a noun to slot in: Arabic
     * agreement runs through the verb and the adjective, so "لا توجد وحدة" and
     * "لا يوجد مخيم" cannot be built from one template.
     *
     * @param  Collection<int, Model>  $existing
     */
    protected function unknownNamed(
        string $intent,
        string $missing,
        string $listLead,
        Collection $existing,
        callable $describe
    ): Answer {
        if ($existing->isEmpty()) {
            return Answer::empty($intent, $missing);
        }

        return Answer::empty($intent, $missing.' '.$listLead, $existing->map($describe)->values()->all());
    }

    protected function statusLabel(Refugee $refugee): string
    {
        return Labels::get('refugee_status', $refugee->status);
    }
}
