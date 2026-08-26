<?php

namespace App\Assistant;

use App\Support\ArabicText;

/**
 * One question typed into the assistant, folded once and then read many times.
 *
 * Every intent inspects the same folded text, so "أين يسكن أحمد" and
 * "اين يسكن احمد" are the same question here — the folding that makes search
 * work in Arabic is the folding that makes matching work.
 */
final class AssistantQuery
{
    /** The question exactly as typed, kept for echoing back to the user. */
    public readonly string $raw;

    /** The folded form every match runs against. */
    public readonly string $text;

    /** @var list<string> */
    public readonly array $words;

    /**
     * The same tokens taken from the untouched text, index for index with
     * `$words`. Matching runs on the folded form, but a name quoted back to the
     * user should be spelled the way they typed it.
     *
     * @var list<string>
     */
    public readonly array $rawWords;

    /**
     * Words that carry no meaning for matching and must not survive into an
     * extracted name: "ابحث عن أحمد" has to yield "احمد", not "عن احمد".
     *
     * @var list<string>
     */
    private const STOPWORDS = [
        'من', 'عن', 'في', 'فى', 'الى', 'على', 'مع', 'هل', 'ما', 'ماذا', 'هو', 'هي',
        'هذا', 'هذه', 'ذلك', 'التي', 'الذي', 'يا', 'لي', 'لو', 'ان', 'او', 'كل',
        'اريد', 'ابحث', 'بحث', 'اعثر', 'جد', 'اعرض', 'اظهر', 'اعطني', 'وين', 'اين',
        'معلومات', 'بيانات', 'تفاصيل', 'ملف', 'سجل', 'حاله', 'وضع', 'يخص', 'الخاص',
        'رجاء', 'لطفا', 'شكرا', 'الرجاء', 'كم', 'عدد', 'اسم', 'باسم', 'المدعو',
        'اللاجي', 'اللاجيه', 'لاجي', 'الشخص', 'المقيم', 'المقيمه', 'سكن', 'سكنه',
        // Arabic conjugates for gender and number, and a form left off this list
        // survives into the extracted name and turns the search into nonsense.
        'يسكن', 'تسكن', 'يسكنون', 'يقيم', 'تقيم', 'يعيش', 'تعيش', 'ساكن', 'ساكنه',
    ];

    public function __construct(string $raw)
    {
        $this->raw = trim($raw);
        $this->text = ArabicText::normalize($raw);
        $this->words = self::tokenize($this->text);

        // Folding can drop a character and, very rarely, empty a token outright,
        // which would slide the two lists out of step. They are only paired when
        // the counts agree; otherwise the folded form is quoted back instead.
        $raw = self::tokenize($this->raw);
        $this->rawWords = count($raw) === count($this->words) ? $raw : $this->words;
    }

    /**
     * @return list<string>
     */
    private static function tokenize(string $value): array
    {
        return array_values(array_filter(
            preg_split('/[\s،؛,.:؟?!"\'()\[\]-]+/u', $value) ?: [],
            static fn (string $word) => $word !== ''
        ));
    }

    /**
     * True when any of the needles appears in the question.
     *
     * Short needles are matched as whole words and longer ones as substrings.
     * The split is deliberate: "مخيم" as a substring usefully also catches
     * "المخيم" and "مخيمات", while "كم" as a substring would fire on "حكم"
     * and "تراكم". Three characters is where Arabic prefixes stop outnumbering
     * the roots they attach to.
     *
     * @param  list<string>  $needles
     */
    public function hasAny(array $needles): bool
    {
        foreach ($needles as $needle) {
            $folded = ArabicText::normalize($needle);

            if ($folded === '') {
                continue;
            }

            $matched = mb_strlen($folded) <= 3
                ? $this->hasWord($folded)
                : str_contains($this->text, $folded);

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whole-word match, tolerating the definite article and the single-letter
     * prefixes Arabic glues onto a word (و، ف، ب، ل، ك).
     */
    public function hasWord(string $folded): bool
    {
        foreach ($this->words as $word) {
            if ($word === $folded || $this->stripPrefixes($word) === $folded) {
                return true;
            }
        }

        return false;
    }

    /**
     * Integers mentioned in the question. Arabic-Indic digits are already ASCII
     * by the time the text is folded, so one pattern covers both ways of typing.
     *
     * @return list<int>
     */
    public function numbers(): array
    {
        preg_match_all('/\d+/u', $this->text, $matches);

        return array_map(static fn (string $digits) => (int) $digits, $matches[0]);
    }

    /**
     * Any token that looks like an identifier rather than a word — a document
     * number, a badge code, a household code. Returned folded and lowercased.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_values(array_filter(
            $this->words,
            static fn (string $word) => preg_match('/\d/u', $word) === 1 && mb_strlen($word) >= 3
        ));
    }

    /**
     * What is left of the question once the intent's own trigger words and the
     * generic stopwords are removed — in practice, the name being asked about.
     *
     * @param  list<string>  $triggers
     */
    public function subject(array $triggers = []): string
    {
        $drop = array_map(
            static fn (string $word) => ArabicText::normalize($word),
            array_merge(self::STOPWORDS, $triggers)
        );

        $kept = array_filter($this->words, function (string $word) use ($drop): bool {
            $bare = $this->stripPrefixes($word);

            return ! in_array($word, $drop, true)
                && ! in_array($bare, $drop, true)
                && preg_match('/^\d+$/u', $word) !== 1;
        });

        return trim(implode(' ', $kept));
    }

    private function stripPrefixes(string $word): string
    {
        $bare = preg_replace('/^(و|ف|ب|ل|ك)?(ال)?/u', '', $word) ?? $word;

        // Only accept the strip when something recognisable is left; otherwise a
        // two-letter word would be eaten down to nothing.
        return mb_strlen($bare) >= 2 ? $bare : $word;
    }
}
