<?php

namespace App\Support;

use App\Models\User;

/**
 * Which functional areas of the system a role is allowed to see.
 *
 * The dashboard decides which cards and charts to render from this, and the
 * assistant decides which questions it will answer from the same map. Keeping
 * one copy is the point: a role that cannot open the aid screens must not be
 * able to ask the assistant for aid figures either, and two lists would drift.
 */
final class RoleScope
{
    private const ALL = ['registration', 'housing', 'medical', 'security', 'aid', 'management'];

    /** Open to any signed-in user; see allows(). */
    public const LOOKUP = 'lookup';

    /**
     * @var array<string, array{title: string, subtitle: string, groups: list<string>}>
     */
    private const PROFILES = [
        'admin' => ['title' => 'لوحة التحكم', 'subtitle' => 'نظرة شاملة على تشغيل النظام.', 'groups' => self::ALL],
        'manager' => ['title' => 'لوحة الإدارة', 'subtitle' => 'مؤشرات تنفيذية مختصرة للمتابعة.', 'groups' => self::ALL],
        'registration_officer' => ['title' => 'لوحة التسجيل', 'subtitle' => 'السكان والأسر وآخر عمليات التسجيل.', 'groups' => ['registration', 'management']],
        'housing_officer' => ['title' => 'لوحة السكن', 'subtitle' => 'الإشغال والوحدات والحالات غير المخصصة.', 'groups' => ['housing', 'management']],
        'aid_officer' => ['title' => 'لوحة المساعدات', 'subtitle' => 'التوزيع والطلبات ومؤشرات الدعم.', 'groups' => ['aid', 'management']],
        'medical_officer' => ['title' => 'لوحة الطب', 'subtitle' => 'المتابعات الطبية والسجلات المفتوحة.', 'groups' => ['medical', 'management']],
        'security_officer' => ['title' => 'لوحة الأمن', 'subtitle' => 'البلاغات والحركة والمخاطر العالية.', 'groups' => ['security', 'management']],
    ];

    /**
     * @return array{title: string, subtitle: string, groups: list<string>}
     */
    public static function profile(?User $user): array
    {
        return self::PROFILES[$user?->role?->name ?? ''] ?? self::PROFILES['manager'];
    }

    /**
     * @return list<string>
     */
    public static function groups(?User $user): array
    {
        return self::profile($user)['groups'];
    }

    /**
     * True when the user's role covers the area.
     *
     * A null user is refused rather than defaulted, so an unauthenticated call
     * path cannot read data by falling through to the manager profile.
     */
    public static function allows(?User $user, string $group): bool
    {
        if ($user === null) {
            return false;
        }

        // "lookup" is the record-level view every signed-in user already has
        // through the top-bar search: finding a person and seeing where they
        // live. Gating it by area would leave the assistant refusing questions
        // the search box on the same page answers.
        if ($group === self::LOOKUP) {
            return true;
        }

        return in_array($group, self::groups($user), true);
    }
}
