<?php

namespace App\Support;

/**
 * Normalisation for Arabic search terms.
 *
 * A clerk typing "احمد" expects to find "أحمد", and someone searching a name
 * copied from a document may bring diacritics or tatweel with it. Matching the
 * raw strings fails in all of those cases, so both the search term and the
 * column being searched are folded to the same reduced form first.
 */
final class ArabicText
{
    /**
     * Character folds applied to both sides of a comparison.
     *
     * Kept as an ordered map because the same list drives the SQL expression in
     * SearchExpression: if the two ever disagree, matching silently breaks.
     *
     * @var array<string, string>
     */
    public const FOLDS = [
        // Alef with any hamza or madda folds to bare alef.
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        // Hamza carriers.
        'ؤ' => 'و', 'ئ' => 'ي', 'ء' => '',
        // Alef maksura and ta marbuta, routinely typed either way.
        'ى' => 'ي', 'ة' => 'ه',
        // Tatweel is decoration and carries no meaning.
        'ـ' => '',
    ];

    /**
     * Arabic-Indic and Eastern Arabic-Indic digits mapped to ASCII, so a
     * document number typed as ١٢٣ matches one stored as 123.
     *
     * @var array<string, string>
     */
    public const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /**
     * Fold a user-supplied term to the form stored comparisons are made against.
     */
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        // Diacritics are stripped with a regex rather than a fold map: there are
        // too many of them, and they never distinguish one name from another.
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;

        $value = strtr($value, self::DIGITS);
        $value = strtr($value, self::FOLDS);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower(trim($value), 'UTF-8');
    }

    /**
     * Fold several fields into the single blob stored in `refugees.search_text`.
     *
     * Parts are separated by spaces so a term spanning two fields ("سميرة الأحمد")
     * still matches, and empty parts are dropped so the blob has no double gaps.
     *
     * @param  array<int, string|null>  $parts
     */
    public static function searchable(array $parts): string
    {
        $folded = array_filter(array_map(
            static fn (?string $part) => self::normalize($part),
            $parts
        ), static fn (string $part) => $part !== '');

        return implode(' ', $folded);
    }

    /**
     * True when the term is too short to be worth running a wildcard scan for.
     */
    public static function isTooShort(?string $value, int $minimum = 2): bool
    {
        return mb_strlen(self::normalize($value)) < $minimum;
    }
}
