<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use App\Models\Shelter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HousingService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications
    ) {}

    /**
     * Move a refugee to a camp/shelter, enforcing capacity and writing the residency trail.
     *
     * The call is a no-op when the refugee already lives there, so it is safe to run on
     * every profile save without polluting the transfer history.
     *
     * @param  string  $errorKey  Field name validation errors are attached to, so both the
     *                            transfer form and the refugee form highlight the right input.
     */
    public function transferRefugee(
        Refugee $refugee,
        int $campId,
        ?int $shelterId,
        ?string $reason = null,
        string $errorKey = 'shelter_id'
    ): Refugee {
        return DB::transaction(function () use ($refugee, $campId, $shelterId, $reason, $errorKey): Refugee {
            $refugee = Refugee::query()->lockForUpdate()->findOrFail($refugee->id);
            $fromCampId = (int) $refugee->current_camp_id;
            $fromShelterId = $refugee->current_shelter_id === null ? null : (int) $refugee->current_shelter_id;
            $shelterId = $shelterId === null ? null : (int) $shelterId;

            if ($fromCampId === $campId && $fromShelterId === $shelterId) {
                return $refugee;
            }

            if ($shelterId) {
                $shelter = Shelter::query()->lockForUpdate()->findOrFail($shelterId);

                if ((int) $shelter->camp_id !== (int) $campId) {
                    throw ValidationException::withMessages([
                        $errorKey => 'الوحدة السكنية لا تتبع للمخيم الهدف.',
                    ]);
                }

                if ($shelter->status !== 'active') {
                    throw ValidationException::withMessages([
                        $errorKey => 'لا يمكن التخصيص إلى وحدة غير فعالة أو تحت الصيانة.',
                    ]);
                }

                if ($this->shelterIsFull($shelter, $refugee->id)) {
                    throw ValidationException::withMessages([
                        $errorKey => 'لا يمكن تخصيص لاجئ إلى وحدة ممتلئة.',
                    ]);
                }
            }

            $refugee->update([
                'current_camp_id' => $campId,
                'current_shelter_id' => $shelterId,
                'housing_status' => $shelterId ? 'assigned' : 'unassigned',
            ]);

            $transferType = match (true) {
                $fromCampId !== $campId => 'camp_transfer',
                $shelterId === null => 'unassignment',
                $fromShelterId === null => 'assignment',
                default => 'shelter_transfer',
            };

            ResidencyTransfer::create([
                'refugee_id' => $refugee->id,
                'from_camp_id' => $fromCampId,
                'to_camp_id' => $campId,
                'from_shelter_id' => $fromShelterId,
                'to_shelter_id' => $shelterId,
                'transfer_type' => $transferType,
                'reason' => $reason,
                'transferred_by' => auth()->id(),
                'transferred_at' => now(),
            ]);

            if (! $shelterId) {
                $this->notifications->forRoles(
                    ['housing_officer', 'manager', 'admin'],
                    'housing_unassigned',
                    'لاجئ بدون سكن',
                    $refugee->full_name.' أصبح بدون وحدة سكنية.',
                    $refugee
                );
            } else {
                $targetShelter = Shelter::find($shelterId);
                if ($targetShelter && $targetShelter->isFull()) {
                    $this->notifications->forRoles(
                        ['housing_officer', 'manager', 'admin'],
                        'shelter_full',
                        'وحدة سكنية ممتلئة',
                        'الوحدة '.$targetShelter->code.' وصلت إلى كامل السعة.',
                        $targetShelter
                    );
                }
            }

            $this->auditLog->log('transfer', 'housing', $refugee, 'تغيير مخيم أو سكن اللاجئ', 'high', [
                'from_camp_id' => $fromCampId,
                'to_camp_id' => $campId,
                'from_shelter_id' => $fromShelterId,
                'to_shelter_id' => $shelterId,
            ]);

            return $refugee->refresh();
        });
    }

    public function transferHousehold(Household $household, int $campId, ?int $shelterId, ?string $reason = null): void
    {
        DB::transaction(function () use ($household, $campId, $shelterId, $reason): void {
            foreach ($household->members()->get() as $member) {
                $this->transferRefugee($member, $campId, $shelterId, $reason ?: 'نقل أسرة كاملة');
            }
        });
    }

    private function shelterIsFull(Shelter $shelter, int $ignoreRefugeeId): bool
    {
        $occupied = Refugee::query()
            ->where('current_shelter_id', $shelter->id)
            ->where('status', 'active')
            ->where('id', '!=', $ignoreRefugeeId)
            ->count();

        return $occupied >= (int) $shelter->capacity;
    }
}
