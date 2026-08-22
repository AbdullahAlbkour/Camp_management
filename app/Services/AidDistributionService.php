<?php

namespace App\Services;

use App\Models\AidDistribution;
use App\Models\Household;
use App\Models\Refugee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AidDistributionService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications
    ) {
    }

    public function distribute(array $data): AidDistribution
    {
        return DB::transaction(function () use ($data): AidDistribution {
            $hasRefugee = ! empty($data['refugee_id']);
            $hasHousehold = ! empty($data['household_id']);

            if ($hasRefugee === $hasHousehold) {
                throw ValidationException::withMessages([
                    'beneficiary' => 'يجب اختيار مستفيد واحد فقط: لاجئ أو أسرة.',
                ]);
            }

            $campId = $data['camp_id'] ?? null;

            if ($hasRefugee) {
                $refugee = Refugee::findOrFail($data['refugee_id']);
                $campId = $campId ?: $refugee->current_camp_id;
            } else {
                $household = Household::with('head')->findOrFail($data['household_id']);
                $campId = $campId ?: $household->head?->current_camp_id;
            }

            if (! $campId) {
                throw ValidationException::withMessages([
                    'camp_id' => 'تعذر تحديد المخيم وقت توزيع المساعدة.',
                ]);
            }

            $duplicate = $this->hasRecentDuplicate($data);

            $distribution = AidDistribution::create([
                'aid_type_id' => $data['aid_type_id'],
                'refugee_id' => $data['refugee_id'] ?? null,
                'household_id' => $data['household_id'] ?? null,
                'camp_id' => $campId,
                'quantity' => $data['quantity'],
                'distribution_date' => $data['distribution_date'],
                'distributed_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($duplicate) {
                $this->notifications->forRoles(
                    ['aid_officer', 'admin'],
                    'aid_duplicate_warning',
                    'تحذير تكرار مساعدة محتمل',
                    'توجد مساعدة مشابهة لنفس المستفيد خلال آخر 30 يومًا.',
                    $distribution
                );
            }

            $this->notifications->forRoles(
                ['aid_officer', 'manager', 'admin'],
                'aid_distributed',
                'توزيع مساعدة جديد',
                'تم تسجيل عملية توزيع مساعدة.',
                $distribution
            );

            $this->auditLog->log('create', 'aid_distributions', $distribution, 'توزيع مساعدة', 'high', $data);

            return $distribution;
        });
    }

    private function hasRecentDuplicate(array $data): bool
    {
        $date = Carbon::parse($data['distribution_date']);

        return AidDistribution::query()
            ->where('aid_type_id', $data['aid_type_id'])
            ->whereBetween('distribution_date', [$date->copy()->subDays(30), $date])
            ->when($data['refugee_id'] ?? null, fn ($query, $id) => $query->where('refugee_id', $id))
            ->when($data['household_id'] ?? null, fn ($query, $id) => $query->where('household_id', $id))
            ->exists();
    }
}
