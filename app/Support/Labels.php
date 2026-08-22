<?php

namespace App\Support;

/**
 * Arabic labels for the enum-ish columns stored in the database.
 *
 * Kept in one place so a value reads the same in a table, an export and a printout.
 */
final class Labels
{
    private const MAPS = [
        'gender' => ['male' => 'ذكر', 'female' => 'أنثى', 'other' => 'غير محدد'],
        'refugee_status' => ['active' => 'فعال', 'inactive' => 'غير فعال', 'archived' => 'مؤرشف'],
        'status' => ['active' => 'فعال', 'inactive' => 'معطل', 'maintenance' => 'صيانة', 'archived' => 'مؤرشف'],
        'housing_status' => ['assigned' => 'مخصص', 'unassigned' => 'بدون سكن'],
        'presence_status' => ['inside' => 'داخل المخيم', 'outside' => 'خارج المخيم'],
        'movement_type' => ['entry' => 'دخول', 'exit' => 'خروج'],
        'severity' => ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'critical' => 'حرجة'],
        'sensitivity' => ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'critical' => 'حرجة'],
        'notification_status' => ['unread' => 'غير مقروء', 'read' => 'مقروء', 'resolved' => 'معالج'],
        'shelter_type' => ['tent' => 'خيمة', 'room' => 'غرفة', 'caravan' => 'كرفان'],
        'transfer_type' => [
            'initial' => 'تسجيل أولي',
            'assignment' => 'تخصيص سكن',
            'unassignment' => 'إلغاء تخصيص',
            'shelter_transfer' => 'نقل بين وحدات',
            'camp_transfer' => 'نقل بين مخيمات',
        ],
        'boolean' => ['1' => 'نعم', '0' => 'لا'],
    ];

    public static function get(string $map, string|int|bool|null $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        return self::MAPS[$map][(string) $value] ?? (string) $value;
    }

    /**
     * @return array<string, string>
     */
    public static function map(string $map): array
    {
        return self::MAPS[$map] ?? [];
    }

    public static function yesNo(mixed $value): string
    {
        return $value ? 'نعم' : 'لا';
    }
}
