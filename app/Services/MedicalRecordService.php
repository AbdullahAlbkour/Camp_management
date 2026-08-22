<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\Refugee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicalRecordService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications
    ) {}

    public function create(array $data): MedicalRecord
    {
        return DB::transaction(function () use ($data): MedicalRecord {
            $this->validateFollowUp($data);

            $refugee = Refugee::findOrFail($data['refugee_id']);

            $record = MedicalRecord::create([
                'refugee_id' => $refugee->id,
                'medical_service_id' => $data['medical_service_id'],
                'camp_id' => $refugee->current_camp_id,
                'record_date' => $data['record_date'],
                'diagnosis' => $data['diagnosis'],
                'notes' => $data['notes'] ?? null,
                'needs_follow_up' => (bool) ($data['needs_follow_up'] ?? false),
                'follow_up_date' => $data['follow_up_date'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            if ($record->needs_follow_up) {
                $this->notifications->forRoles(
                    ['medical_officer', 'manager', 'admin'],
                    'medical_follow_up',
                    'متابعة طبية مطلوبة',
                    'يوجد سجل طبي يحتاج متابعة للاجئ '.$refugee->full_name.'.',
                    $record
                );
            }

            $this->auditLog->log('create', 'medical_records', $record, 'إضافة سجل طبي', 'high', [
                'refugee_id' => $refugee->id,
                'record_date' => $data['record_date'],
                'needs_follow_up' => $record->needs_follow_up,
            ]);

            return $record;
        });
    }

    public function update(MedicalRecord $record, array $data): MedicalRecord
    {
        return DB::transaction(function () use ($record, $data): MedicalRecord {
            $this->validateFollowUp($data);
            if (! empty($data['refugee_id'])) {
                $data['camp_id'] = Refugee::findOrFail($data['refugee_id'])->current_camp_id;
            }
            $record->update($data);

            if ($record->needs_follow_up) {
                $this->notifications->forRoles(
                    ['medical_officer', 'manager', 'admin'],
                    'medical_follow_up',
                    'تحديث متابعة طبية',
                    'تم تحديث سجل طبي يحتاج متابعة.',
                    $record
                );
            }

            $this->auditLog->log('update', 'medical_records', $record, 'تعديل سجل طبي', 'high', [
                'record_id' => $record->id,
                'needs_follow_up' => $record->needs_follow_up,
            ]);

            return $record->refresh();
        });
    }

    private function validateFollowUp(array $data): void
    {
        if (! empty($data['needs_follow_up']) && empty($data['follow_up_date'])) {
            throw ValidationException::withMessages([
                'follow_up_date' => 'تاريخ المتابعة مطلوب عند تحديد أن الحالة تحتاج متابعة.',
            ]);
        }
    }
}
