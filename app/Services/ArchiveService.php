<?php

namespace App\Services;

use App\Models\Camp;
use App\Models\Household;
use App\Models\Organization;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Archiving (soft delete) for reference data.
 *
 * Nothing here is a real delete: rows stay in place so historical distributions,
 * medical records and movements keep resolving to a name. What this does enforce
 * is that a record still in active use cannot be archived out from under the
 * data that points at it.
 */
class ArchiveService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function archive(Model $model, ?string $reason = null): void
    {
        DB::transaction(function () use ($model, $reason): void {
            $this->guardAgainstActiveDependents($model);

            $model->delete();

            $this->auditLog->log(
                'archive',
                $this->module($model),
                $model,
                'أرشفة سجل'.($reason ? ': '.$reason : ''),
                'high',
                ['reason' => $reason]
            );
        });
    }

    public function restore(Model $model): void
    {
        DB::transaction(function () use ($model): void {
            $model->restore();

            $this->auditLog->log('restore', $this->module($model), $model, 'استرجاع سجل من الأرشيف', 'high');
        });
    }

    /**
     * Refuse the archive while something active still depends on the record.
     */
    private function guardAgainstActiveDependents(Model $model): void
    {
        $blocker = match (true) {
            $model instanceof Camp => $this->campBlocker($model),
            $model instanceof Shelter => $this->shelterBlocker($model),
            $model instanceof Household => $this->householdBlocker($model),
            $model instanceof Refugee => $this->refugeeBlocker($model),
            $model instanceof Organization => $this->organizationBlocker($model),
            // Aid types, medical services and checkpoints only appear on historical
            // rows, which soft deletion keeps resolvable, so nothing blocks them.
            default => null,
        };

        if ($blocker !== null) {
            throw ValidationException::withMessages(['archive' => $blocker]);
        }
    }

    private function campBlocker(Camp $camp): ?string
    {
        $refugees = Refugee::where('current_camp_id', $camp->id)->where('status', 'active')->count();

        if ($refugees > 0) {
            return 'لا يمكن أرشفة المخيم: ما زال يضم '.$refugees.' لاجئًا فعالًا. انقلهم أولًا.';
        }

        return Shelter::where('camp_id', $camp->id)->exists()
            ? 'لا يمكن أرشفة المخيم قبل أرشفة وحداته السكنية.'
            : null;
    }

    private function shelterBlocker(Shelter $shelter): ?string
    {
        $occupants = Refugee::where('current_shelter_id', $shelter->id)->where('status', 'active')->count();

        return $occupants > 0
            ? 'لا يمكن أرشفة الوحدة السكنية: ما زال فيها '.$occupants.' من الساكنين.'
            : null;
    }

    private function householdBlocker(Household $household): ?string
    {
        $members = $household->members()->where('status', 'active')->count();

        return $members > 0
            ? 'لا يمكن أرشفة الأسرة: ما زالت تضم '.$members.' فردًا. أزل الأفراد أولًا.'
            : null;
    }

    private function refugeeBlocker(Refugee $refugee): ?string
    {
        return $refugee->housing_status === 'assigned'
            ? 'لا يمكن أرشفة لاجئ ما زال مخصصًا لوحدة سكنية. ألغِ التخصيص أولًا.'
            : null;
    }

    private function organizationBlocker(Organization $organization): ?string
    {
        return $organization->aidTypes()->where('status', 'active')->exists()
            ? 'لا يمكن أرشفة الجهة الداعمة: لديها أنواع مساعدات فعالة.'
            : null;
    }

    private function module(Model $model): string
    {
        return str($model::class)->classBasename()->snake()->plural()->value();
    }
}
