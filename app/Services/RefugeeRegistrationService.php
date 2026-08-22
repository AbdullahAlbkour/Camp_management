<?php

namespace App\Services;

use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use App\Models\Shelter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefugeeRegistrationService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications
    ) {
    }

    public function register(array $data): Refugee
    {
        return DB::transaction(function () use ($data): Refugee {
            $shelterId = $data['current_shelter_id'] ?? null;

            if ($shelterId) {
                $shelter = Shelter::query()->lockForUpdate()->findOrFail($shelterId);

                if ((int) $shelter->camp_id !== (int) $data['current_camp_id']) {
                    throw ValidationException::withMessages([
                        'current_shelter_id' => 'الوحدة السكنية المختارة لا تتبع للمخيم الحالي.',
                    ]);
                }

                if ($shelter->isFull()) {
                    throw ValidationException::withMessages([
                        'current_shelter_id' => 'لا يمكن تخصيص وحدة سكنية ممتلئة.',
                    ]);
                }
            }

            $data['status'] = $data['status'] ?? 'active';
            $data['presence_status'] = $data['presence_status'] ?? 'inside';
            $data['housing_status'] = $shelterId ? 'assigned' : 'unassigned';

            $refugee = Refugee::create($data);

            ResidencyTransfer::create([
                'refugee_id' => $refugee->id,
                'from_camp_id' => null,
                'to_camp_id' => $refugee->current_camp_id,
                'from_shelter_id' => null,
                'to_shelter_id' => $refugee->current_shelter_id,
                'transfer_type' => 'initial',
                'reason' => 'تسجيل أولي',
                'transferred_by' => auth()->id(),
                'transferred_at' => now(),
            ]);

            if (! $shelterId) {
                $this->notifications->forRoles(
                    ['housing_officer', 'manager', 'admin'],
                    'housing_unassigned',
                    'لاجئ جديد بدون سكن',
                    'تم تسجيل '.$refugee->full_name.' بدون وحدة سكنية.',
                    $refugee
                );
            } elseif (isset($shelter) && $shelter->refresh()->isFull()) {
                $this->notifications->forRoles(
                    ['housing_officer', 'manager', 'admin'],
                    'shelter_full',
                    'وحدة سكنية ممتلئة',
                    'الوحدة '.$shelter->code.' وصلت إلى كامل السعة.',
                    $shelter
                );
            }

            $this->auditLog->log('create', 'refugees', $refugee, 'تسجيل لاجئ جديد', 'high', $data);

            return $refugee;
        });
    }

    public function possibleDuplicates(array $data)
    {
        return Refugee::query()
            ->when($data['document_number'] ?? null, function ($query, string $documentNumber): void {
                $query->orWhere('document_number', $documentNumber);
            })
            ->when($data['first_name'] ?? null, function ($query, string $firstName): void {
                $query->orWhere('first_name', 'like', '%'.$firstName.'%');
            })
            ->when($data['last_name'] ?? null, function ($query, string $lastName): void {
                $query->orWhere('last_name', 'like', '%'.$lastName.'%');
            })
            ->with(['currentCamp', 'currentShelter'])
            ->limit(10)
            ->get();
    }
}
