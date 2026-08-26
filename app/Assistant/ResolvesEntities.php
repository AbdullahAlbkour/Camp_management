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
     * A camp's name with the word "مخيم" in front of it — unless the name
     * already starts with it, which most do. Without the check the answers read
     * "في مخيم مخيم السلام".
     */
    protected function campLabel(Camp $camp): string
    {
        return str_starts_with(trim($camp->name), 'مخيم') ? $camp->name : 'مخيم '.$camp->name;
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
