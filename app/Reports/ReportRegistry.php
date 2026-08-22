<?php

namespace App\Reports;

use App\Models\AidDistribution;
use App\Models\EntryExitLog;
use App\Models\Household;
use App\Models\MedicalRecord;
use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use App\Models\SecurityReport;
use App\Models\Shelter;
use App\Models\User;
use App\Support\Labels;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Every report the system can produce, with real column headings.
 *
 * The registry is the single definition used by the on-screen table, the CSV and
 * Excel exports and the printable view, so all four stay in step.
 */
class ReportRegistry
{
    public const DEFAULT_REPORT = 'refugees';

    /**
     * Report key => [heading, roles allowed to run it].
     */
    private const CATALOGUE = [
        'refugees' => ['اللاجئون', []],
        'households' => ['الأسر', []],
        'shelters' => ['السكن والوحدات', ['admin', 'manager', 'housing_officer']],
        'transfers' => ['الانتقالات', ['admin', 'manager', 'housing_officer']],
        'aid' => ['المساعدات', ['admin', 'manager', 'aid_officer']],
        'medical' => ['السجلات الطبية', ['admin', 'manager', 'medical_officer']],
        'movement' => ['حركة الدخول والخروج', ['admin', 'manager', 'security_officer']],
        'security' => ['التقارير الأمنية', ['admin', 'manager', 'security_officer']],
    ];

    /**
     * Reports the given user is allowed to open, as key => heading.
     *
     * @return array<string, string>
     */
    public function availableFor(?User $user): array
    {
        $available = [];

        foreach (self::CATALOGUE as $key => [$label, $roles]) {
            if ($roles === [] || ($user !== null && $user->hasAnyRole($roles))) {
                $available[$key] = $label;
            }
        }

        return $available;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, self::CATALOGUE);
    }

    /**
     * Build a report, applying the filters and the viewer's column-level restrictions.
     *
     * @param  array{camp_id?: string|int|null, from?: string|null, to?: string|null}  $filters
     */
    public function build(string $key, array $filters, ?User $user): ReportDefinition
    {
        if (! $this->has($key)) {
            throw new NotFoundHttpException('تقرير غير معروف: '.$key);
        }

        [$label, $roles] = self::CATALOGUE[$key];

        $campId = $filters['camp_id'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        [$columns, $query] = match ($key) {
            'households' => $this->households(),
            'shelters' => $this->shelters($campId),
            'transfers' => $this->transfers($from, $to),
            'aid' => $this->aid($campId, $from, $to),
            'medical' => $this->medical($campId, $from, $to, $user),
            'movement' => $this->movement($campId, $from, $to),
            'security' => $this->security($campId, $from, $to, $user),
            default => $this->refugees($campId),
        };

        return new ReportDefinition($key, $label, $columns, $query, $roles);
    }

    /**
     * @return array{array<string, ReportColumn>, Builder}
     */
    private function refugees(mixed $campId): array
    {
        $columns = [
            'id' => ReportColumn::make('الرقم', fn (Refugee $r) => $r->id),
            'name' => ReportColumn::make('الاسم الكامل', fn (Refugee $r) => $r->full_name),
            'gender' => ReportColumn::make('الجنس', fn (Refugee $r) => Labels::get('gender', $r->gender)),
            'date_of_birth' => ReportColumn::make('تاريخ الميلاد', fn (Refugee $r) => $r->date_of_birth?->format('Y-m-d')),
            'nationality' => ReportColumn::make('الجنسية', fn (Refugee $r) => $r->nationality),
            'document_number' => ReportColumn::make('رقم الوثيقة', fn (Refugee $r) => $r->document_number),
            'phone' => ReportColumn::make('الهاتف', fn (Refugee $r) => $r->phone),
            'camp' => ReportColumn::make('المخيم', fn (Refugee $r) => $r->currentCamp?->name),
            'shelter' => ReportColumn::make('الوحدة السكنية', fn (Refugee $r) => $r->currentShelter?->display_name),
            'housing_status' => ReportColumn::make('حالة السكن', fn (Refugee $r) => Labels::get('housing_status', $r->housing_status)),
            'presence_status' => ReportColumn::make('حالة الوجود', fn (Refugee $r) => Labels::get('presence_status', $r->presence_status)),
            'household' => ReportColumn::make('رمز الأسرة', fn (Refugee $r) => $r->household?->household_code),
            'status' => ReportColumn::make('الحالة', fn (Refugee $r) => Labels::get('refugee_status', $r->status)),
        ];

        $query = Refugee::query()
            ->with(['currentCamp', 'currentShelter', 'household'])
            ->when($campId, fn (Builder $q) => $q->where('current_camp_id', $campId))
            ->orderByDesc('id');

        return [$columns, $query];
    }

    /**
     * @return array{array<string, ReportColumn>, Builder}
     */
    private function households(): array
    {
        $columns = [
            'id' => ReportColumn::make('الرقم', fn (Household $h) => $h->id),
            'code' => ReportColumn::make('رمز الأسرة', fn (Household $h) => $h->household_code),
            'head' => ReportColumn::make('رب الأسرة', fn (Household $h) => $h->head?->full_name),
            'members' => ReportColumn::make('عدد الأفراد', fn (Household $h) => (int) $h->members_count),
            'status' => ReportColumn::make('الحالة', fn (Household $h) => Labels::get('status', $h->status)),
            'created_at' => ReportColumn::make('تاريخ الإنشاء', fn (Household $h) => $h->created_at?->format('Y-m-d')),
        ];

        $query = Household::query()->with('head')->withCount('members')->orderByDesc('id');

        return [$columns, $query];
    }

    /**
     * @return array{array<string, ReportColumn>, Builder}
     */
    private function shelters(mixed $campId): array
    {
        $columns = [
            'id' => ReportColumn::make('الرقم', fn (Shelter $s) => $s->id),
            'camp' => ReportColumn::make('المخيم', fn (Shelter $s) => $s->camp?->name),
            'code' => ReportColumn::make('الرمز', fn (Shelter $s) => $s->code),
            'type' => ReportColumn::make('النوع', fn (Shelter $s) => Labels::get('shelter_type', $s->type)),
            'capacity' => ReportColumn::make('السعة', fn (Shelter $s) => (int) $s->capacity),
            'occupied' => ReportColumn::make('المشغول', fn (Shelter $s) => (int) $s->refugees_count),
            'available' => ReportColumn::make('المتاح', fn (Shelter $s) => max(0, (int) $s->capacity - (int) $s->refugees_count)),
            'status' => ReportColumn::make('الحالة', fn (Shelter $s) => Labels::get('status', $s->status)),
        ];

        $query = Shelter::query()
            ->with('camp')
            ->withCount(['refugees' => fn ($q) => $q->where('status', 'active')])
            ->when($campId, fn (Builder $q) => $q->where('camp_id', $campId))
            ->orderBy('camp_id')
            ->orderBy('code');

        return [$columns, $query];
    }

    /**
     * @return array{array<string, ReportColumn>, Builder}
     */
    private function transfers(mixed $from, mixed $to): array
    {
        $columns = [
            'id' => ReportColumn::make('الرقم', fn (ResidencyTransfer $t) => $t->id),
            'refugee' => ReportColumn::make('اللاجئ', fn (ResidencyTransfer $t) => $t->refugee?->full_name),
            'from_camp' => ReportColumn::make('من مخيم', fn (ResidencyTransfer $t) => $t->fromCamp?->name),
            'to_camp' => ReportColumn::make('إلى مخيم', fn (ResidencyTransfer $t) => $t->toCamp?->name),
            'from_shelter' => ReportColumn::make('من وحدة', fn (ResidencyTransfer $t) => $t->fromShelter?->display_name),
            'to_shelter' => ReportColumn::make('إلى وحدة', fn (ResidencyTransfer $t) => $t->toShelter?->display_name),
            'type' => ReportColumn::make('نوع الانتقال', fn (ResidencyTransfer $t) => Labels::get('transfer_type', $t->transfer_type)),
            'reason' => ReportColumn::make('السبب', fn (ResidencyTransfer $t) => $t->reason),
            'by' => ReportColumn::make('نفذها', fn (ResidencyTransfer $t) => $t->transferredBy?->name),
            'at' => ReportColumn::make('التاريخ', fn (ResidencyTransfer $t) => $t->transferred_at?->format('Y-m-d H:i')),
        ];

        $query = ResidencyTransfer::query()
            ->with(['refugee', 'fromCamp', 'toCamp', 'fromShelter', 'toShelter', 'transferredBy'])
            ->when($from, fn (Builder $q) => $q->whereDate('transferred_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('transferred_at', '<=', $to))
            ->orderByDesc('transferred_at');

        return [$columns, $query];
    }

    /**
     * @return array{array<string, ReportColumn>, Builder}
     */
    private function aid(mixed $campId, mixed $from, mixed $to): array
    {
        $columns = [
            'id' => ReportColumn::make('الرقم', fn (AidDistribution $a) => $a->id),
            'aid_type' => ReportColumn::make('نوع المساعدة', fn (AidDistribution $a) => $a->aidType?->name),
            'organization' => ReportColumn::make('الجهة الداعمة', fn (AidDistribution $a) => $a->aidType?->organization?->name),
            'beneficiary' => ReportColumn::make(
                'المستفيد',
                fn (AidDistribution $a) => $a->refugee?->full_name ?? $a->household?->household_code
            ),
            'beneficiary_type' => ReportColumn::make(
                'نوع المستفيد',
                fn (AidDistribution $a) => $a->refugee_id ? 'فرد' : 'أسرة'
            ),
            'camp' => ReportColumn::make('المخيم', fn (AidDistribution $a) => $a->camp?->name),
            'quantity' => ReportColumn::make('الكمية', fn (AidDistribution $a) => (float) $a->quantity),
            'unit' => ReportColumn::make('الوحدة', fn (AidDistribution $a) => $a->aidType?->unit),
            'date' => ReportColumn::make('تاريخ التوزيع', fn (AidDistribution $a) => $this->date($a->distribution_date)),
            'by' => ReportColumn::make('سجلها', fn (AidDistribution $a) => $a->distributedBy?->name),
        ];

        $query = AidDistribution::query()
            ->with(['aidType.organization', 'refugee', 'household', 'camp', 'distributedBy'])
            ->when($campId, fn (Builder $q) => $q->where('camp_id', $campId))
            ->when($from, fn (Builder $q) => $q->whereDate('distribution_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('distribution_date', '<=', $to))
            ->orderByDesc('distribution_date');

        return [$columns, $query];
    }

    /**
     * @return array{array<string, ReportColumn>, Builder}
     */
    private function medical(mixed $campId, mixed $from, mixed $to, ?User $user): array
    {
        $columns = [
            'id' => ReportColumn::make('الرقم', fn (MedicalRecord $m) => $m->id),
            'refugee' => ReportColumn::make('اللاجئ', fn (MedicalRecord $m) => $m->refugee?->full_name),
            'service' => ReportColumn::make('الخدمة', fn (MedicalRecord $m) => $m->medicalService?->name),
            'camp' => ReportColumn::make('المخيم', fn (MedicalRecord $m) => $m->camp?->name),
            'date' => ReportColumn::make('التاريخ', fn (MedicalRecord $m) => $this->date($m->record_date)),
        ];

        // The diagnosis is the sensitive part of a medical record: a manager may see that a
        // visit happened and whether follow-up is due, but not what the patient was treated for.
        if ($this->canSeeClinicalDetail($user)) {
            $columns['diagnosis'] = ReportColumn::make('التشخيص', fn (MedicalRecord $m) => $m->diagnosis);
        }

        $columns['needs_follow_up'] = ReportColumn::make('يحتاج متابعة', fn (MedicalRecord $m) => Labels::yesNo($m->needs_follow_up));
        $columns['follow_up_date'] = ReportColumn::make('تاريخ المتابعة', fn (MedicalRecord $m) => $this->date($m->follow_up_date));
        $columns['by'] = ReportColumn::make('سجلها', fn (MedicalRecord $m) => $m->recordedBy?->name);

        $query = MedicalRecord::query()
            ->with(['refugee', 'medicalService', 'camp', 'recordedBy'])
            ->when($campId, fn (Builder $q) => $q->where('camp_id', $campId))
            ->when($from, fn (Builder $q) => $q->whereDate('record_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('record_date', '<=', $to))
            ->orderByDesc('record_date');

        return [$columns, $query];
    }

    /**
     * @return array{array<string, ReportColumn>, Builder}
     */
    private function movement(mixed $campId, mixed $from, mixed $to): array
    {
        $columns = [
            'id' => ReportColumn::make('الرقم', fn (EntryExitLog $e) => $e->id),
            'refugee' => ReportColumn::make('اللاجئ', fn (EntryExitLog $e) => $e->refugee?->full_name),
            'camp' => ReportColumn::make('المخيم', fn (EntryExitLog $e) => $e->camp?->name),
            'checkpoint' => ReportColumn::make('نقطة التفتيش', fn (EntryExitLog $e) => $e->checkpoint?->name),
            'type' => ReportColumn::make('نوع الحركة', fn (EntryExitLog $e) => Labels::get('movement_type', $e->movement_type)),
            'at' => ReportColumn::make('التاريخ والوقت', fn (EntryExitLog $e) => $this->dateTime($e->movement_datetime)),
            'reason' => ReportColumn::make('السبب', fn (EntryExitLog $e) => $e->reason),
            'by' => ReportColumn::make('سجلها', fn (EntryExitLog $e) => $e->recordedBy?->name),
        ];

        $query = EntryExitLog::query()
            ->with(['refugee', 'camp', 'checkpoint', 'recordedBy'])
            ->when($campId, fn (Builder $q) => $q->where('camp_id', $campId))
            ->when($from, fn (Builder $q) => $q->whereDate('movement_datetime', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('movement_datetime', '<=', $to))
            ->orderByDesc('movement_datetime');

        return [$columns, $query];
    }

    /**
     * @return array{array<string, ReportColumn>, Builder}
     */
    private function security(mixed $campId, mixed $from, mixed $to, ?User $user): array
    {
        $columns = [
            'id' => ReportColumn::make('الرقم', fn (SecurityReport $s) => $s->id),
            'refugee' => ReportColumn::make('اللاجئ', fn (SecurityReport $s) => $s->refugee?->full_name),
            'camp' => ReportColumn::make('المخيم', fn (SecurityReport $s) => $s->camp?->name),
            'incident_type' => ReportColumn::make('نوع الحادثة', fn (SecurityReport $s) => $s->incident_type),
            'severity' => ReportColumn::make('الخطورة', fn (SecurityReport $s) => Labels::get('severity', $s->severity)),
            'date' => ReportColumn::make('تاريخ التقرير', fn (SecurityReport $s) => $this->date($s->report_date)),
        ];

        // As with diagnoses, the narrative of an incident stays with the security team.
        if ($this->canSeeIncidentDetail($user)) {
            $columns['description'] = ReportColumn::make('الوصف', fn (SecurityReport $s) => $s->description);
            $columns['action_taken'] = ReportColumn::make('الإجراء المتخذ', fn (SecurityReport $s) => $s->action_taken);
        }

        $columns['by'] = ReportColumn::make('أبلغ عنها', fn (SecurityReport $s) => $s->reportedBy?->name);

        $query = SecurityReport::query()
            ->with(['refugee', 'camp', 'reportedBy'])
            ->when($campId, fn (Builder $q) => $q->where('camp_id', $campId))
            ->when($from, fn (Builder $q) => $q->whereDate('report_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('report_date', '<=', $to))
            ->orderByDesc('report_date');

        return [$columns, $query];
    }

    private function canSeeClinicalDetail(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(['admin', 'medical_officer']);
    }

    private function canSeeIncidentDetail(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(['admin', 'security_officer']);
    }

    private function date(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : ($value ? (string) $value : null);
    }

    private function dateTime(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i') : ($value ? (string) $value : null);
    }
}
