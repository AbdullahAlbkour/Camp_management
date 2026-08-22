<?php

namespace App\Services;

use App\Models\Checkpoint;
use App\Models\EntryExitLog;
use App\Models\Refugee;
use App\Models\SecurityReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovementSecurityService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications
    ) {}

    public function recordMovement(array $data): EntryExitLog
    {
        return DB::transaction(function () use ($data): EntryExitLog {
            $refugee = Refugee::query()->lockForUpdate()->findOrFail($data['refugee_id']);
            $checkpoint = Checkpoint::findOrFail($data['checkpoint_id']);

            if ((int) $checkpoint->camp_id !== (int) $refugee->current_camp_id) {
                throw ValidationException::withMessages([
                    'checkpoint_id' => 'نقطة التفتيش لا تتبع للمخيم الحالي للاجئ.',
                ]);
            }

            $movement = EntryExitLog::create([
                'refugee_id' => $refugee->id,
                'camp_id' => $refugee->current_camp_id,
                'checkpoint_id' => $checkpoint->id,
                'movement_type' => $data['movement_type'],
                'movement_datetime' => $data['movement_datetime'],
                'reason' => $data['reason'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            $refugee->update([
                'presence_status' => $data['movement_type'] === 'exit' ? 'outside' : 'inside',
            ]);

            $this->auditLog->log('create', 'entry_exit_logs', $movement, 'تسجيل حركة دخول أو خروج', 'high', [
                'refugee_id' => $refugee->id,
                'movement_type' => $data['movement_type'],
            ]);

            return $movement;
        });
    }

    public function createSecurityReport(array $data): SecurityReport
    {
        return DB::transaction(function () use ($data): SecurityReport {
            $refugee = Refugee::findOrFail($data['refugee_id']);

            $report = SecurityReport::create([
                'refugee_id' => $refugee->id,
                'camp_id' => $refugee->current_camp_id,
                'incident_type' => $data['incident_type'],
                'severity' => $data['severity'],
                'report_date' => $data['report_date'],
                'description' => $data['description'],
                'action_taken' => $data['action_taken'] ?? null,
                'reported_by' => auth()->id(),
            ]);

            if (in_array($report->severity, ['high', 'critical'], true)) {
                $this->notifications->forRoles(
                    ['security_officer', 'manager', 'admin'],
                    'security_high_risk',
                    'تقرير أمني عالي الخطورة',
                    'تم تسجيل تقرير أمني بدرجة '.$report->severity.'.',
                    $report
                );
            }

            $this->auditLog->log('create', 'security_reports', $report, 'إضافة تقرير أمني', 'high', [
                'refugee_id' => $refugee->id,
                'severity' => $report->severity,
                'incident_type' => $report->incident_type,
            ]);

            return $report;
        });
    }
}
