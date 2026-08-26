<?php

namespace App\Assistant;

use Illuminate\Support\Carbon;

/**
 * The time spans a question can name, and the date range each resolves to.
 *
 * Shared rather than owned by one intent: "مساعدات الشهر الماضي" is an
 * aggregate question precisely *because* it names a period, so the intent that
 * answers about one person needs to recognise the same phrases in order to
 * stand down.
 */
final class TimeWindow
{
    /**
     * Longest phrase first, so "الشهر الماضي" is not read as "الشهر" and
     * silently answered for the current month.
     *
     * @var array<string, string>
     */
    private const PHRASES = [
        'الشهر الماضي' => 'last_month',
        'الشهر السابق' => 'last_month',
        'الأسبوع الماضي' => 'last_week',
        'هذا الأسبوع' => 'week',
        'هذه السنة' => 'year',
        'هذا العام' => 'year',
        'هذا الشهر' => 'month',
        'اليوم' => 'today',
        'أمس' => 'yesterday',
    ];

    /**
     * The window named in the question, or null when none is.
     */
    public static function in(AssistantQuery $query): ?string
    {
        foreach (self::PHRASES as $phrase => $window) {
            if ($query->hasAny([$phrase])) {
                return $window;
            }
        }

        return null;
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public static function range(?string $window): array
    {
        return match ($window) {
            'today' => [now()->startOfDay(), now()->endOfDay(), 'اليوم'],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), 'أمس'],
            'week' => [now()->startOfWeek(), now()->endOfWeek(), 'هذا الأسبوع'],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek(), 'الأسبوع الماضي'],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth(), 'الشهر الماضي'],
            'year' => [now()->startOfYear(), now()->endOfYear(), 'هذا العام'],
            default => [now()->startOfMonth(), now()->endOfMonth(), 'هذا الشهر'],
        };
    }
}
