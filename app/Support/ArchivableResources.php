<?php

namespace App\Support;

use App\Models\AidType;
use App\Models\Camp;
use App\Models\Checkpoint;
use App\Models\Household;
use App\Models\MedicalService;
use App\Models\Organization;
use App\Models\Refugee;
use App\Models\Shelter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What can be archived, and who may archive it.
 *
 * Kept here rather than inside ArchiveController because the delete button now
 * appears on eight index screens: the views have to ask the same question the
 * controller answers, and a role list copied into each Blade file would drift
 * from the one that actually enforces it.
 */
final class ArchivableResources
{
    /**
     * Resource key => [model class, index route to return to, Arabic label, roles allowed].
     */
    private const MAP = [
        'camps' => [Camp::class, 'camps.index', 'المخيمات', ['admin', 'housing_officer']],
        'shelters' => [Shelter::class, 'shelters.index', 'الوحدات السكنية', ['admin', 'housing_officer']],
        'refugees' => [Refugee::class, 'refugees.index', 'اللاجئون', ['admin', 'registration_officer']],
        'households' => [Household::class, 'households.index', 'الأسر', ['admin', 'registration_officer']],
        'organizations' => [Organization::class, 'aid.organizations', 'الجهات الداعمة', ['admin', 'aid_officer']],
        'aid_types' => [AidType::class, 'aid.types', 'أنواع المساعدات', ['admin', 'aid_officer']],
        'medical_services' => [MedicalService::class, 'medical.services', 'الخدمات الطبية', ['admin', 'medical_officer']],
        'checkpoints' => [Checkpoint::class, 'security.checkpoints', 'نقاط التفتيش', ['admin', 'security_officer']],
    ];

    public static function has(string $resource): bool
    {
        return isset(self::MAP[$resource]);
    }

    /**
     * @return class-string<Model>
     */
    public static function model(string $resource): string
    {
        return self::MAP[$resource][0];
    }

    public static function indexRoute(string $resource): string
    {
        return self::MAP[$resource][1];
    }

    /**
     * True when this user may archive and restore this kind of record.
     *
     * A null user is refused rather than defaulted, so an unauthenticated path
     * cannot reach the button or the route behind it.
     */
    public static function allows(?User $user, string $resource): bool
    {
        return $user !== null
            && self::has($resource)
            && $user->hasAnyRole(self::MAP[$resource][3]);
    }

    /**
     * The archives this user may browse, as key => Arabic label.
     *
     * @return array<string, string>
     */
    public static function labelsFor(?User $user): array
    {
        $available = [];

        foreach (self::MAP as $key => [, , $label]) {
            if (self::allows($user, $key)) {
                $available[$key] = $label;
            }
        }

        return $available;
    }
}
