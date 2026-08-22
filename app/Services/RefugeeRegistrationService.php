<?php

namespace App\Services;

use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use App\Models\Shelter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefugeeRegistrationService
{
    /**
     * Minimum evidence for a record to be shown as a possible duplicate: one
     * identifier match, or a full first-and-last name match.
     */
    private const DUPLICATE_THRESHOLD = 50;

    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications
    ) {}

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

    /**
     * Records that plausibly describe the same person as $data.
     *
     * Matching is evidence-based rather than a loose name search. A shared first
     * name is not evidence — in a camp of thousands it flags almost every
     * registration, and a warning that always fires is a warning nobody reads.
     * A duplicate has to be supported by an identifier (document or phone) or by
     * a full name match, and additional agreement raises the confidence shown.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Refugee>
     */
    public function possibleDuplicates(array $data): Collection
    {
        $document = trim((string) ($data['document_number'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $fatherName = trim((string) ($data['father_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $dateOfBirth = $data['date_of_birth'] ?? null;

        $hasFullName = $firstName !== '' && $lastName !== '';

        if ($document === '' && $phone === '' && ! $hasFullName) {
            return collect();
        }

        $candidates = Refugee::query()
            ->when($data['id'] ?? null, fn (Builder $query, $id) => $query->whereKeyNot($id))
            ->where(function (Builder $query) use ($document, $phone, $hasFullName, $firstName, $lastName): void {
                if ($document !== '') {
                    $query->orWhere('document_number', $document);
                }

                if ($phone !== '') {
                    $query->orWhere('phone', $phone);
                }

                if ($hasFullName) {
                    $query->orWhere(fn (Builder $inner) => $inner
                        ->where('first_name', $firstName)
                        ->where('last_name', $lastName));
                }
            })
            ->with(['currentCamp', 'currentShelter'])
            ->limit(50)
            ->get();

        return $candidates
            ->map(function (Refugee $candidate) use ($document, $phone, $firstName, $fatherName, $lastName, $dateOfBirth): Refugee {
                $reasons = [];
                $score = 0;

                if ($document !== '' && $candidate->document_number === $document) {
                    $reasons[] = 'رقم الوثيقة مطابق';
                    $score += 100;
                }

                if ($phone !== '' && $candidate->phone === $phone) {
                    $reasons[] = 'رقم الهاتف مطابق';
                    $score += 60;
                }

                if ($firstName !== '' && $lastName !== ''
                    && $candidate->first_name === $firstName
                    && $candidate->last_name === $lastName) {
                    $reasons[] = 'الاسم الأول واسم العائلة متطابقان';
                    $score += 50;

                    if ($fatherName !== '' && $candidate->father_name === $fatherName) {
                        $reasons[] = 'اسم الأب مطابق';
                        $score += 20;
                    }
                }

                if ($dateOfBirth && $candidate->date_of_birth?->isSameDay(Carbon::parse($dateOfBirth))) {
                    $reasons[] = 'تاريخ الميلاد مطابق';
                    $score += 30;
                }

                $candidate->setAttribute('match_score', $score);
                $candidate->setAttribute('match_reasons', $reasons);

                return $candidate;
            })
            ->filter(fn (Refugee $candidate) => $candidate->match_score >= self::DUPLICATE_THRESHOLD)
            ->sortByDesc('match_score')
            ->take(10)
            ->values();
    }
}
