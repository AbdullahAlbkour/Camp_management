<?php

namespace App\Console\Commands;

use App\Models\MedicalRecord;
use App\Models\Refugee;
use App\Models\Shelter;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The daily sweep for conditions nobody would otherwise be told about.
 *
 * Most notifications in the system are raised at the moment something happens.
 * These are the opposite: they are about time passing — a follow-up date that has
 * arrived, someone still unhoused a week later, someone who left and has not come
 * back. Notifications are deduplicated by NotificationService, so running this
 * more than once a day does not spam the officers' inbox.
 */
class SendDailyDigest extends Command
{
    protected $signature = 'camps:daily-digest
                            {--unassigned-days=3 : Days unhoused before a refugee is flagged}
                            {--absence-days=7 : Days outside the camp before an absence is flagged}';

    protected $description = 'Raise notifications for medical follow-ups due, unhoused refugees, long absences and full shelters';

    public function handle(NotificationService $notifications): int
    {
        $raised = 0;
        $raised += $this->medicalFollowUps($notifications);
        $raised += $this->unhousedRefugees($notifications, (int) $this->option('unassigned-days'));
        $raised += $this->longAbsences($notifications, (int) $this->option('absence-days'));
        $raised += $this->fullShelters($notifications);

        $this->info($raised === 0
            ? 'لا توجد تنبيهات جديدة اليوم.'
            : 'تم رفع '.$raised.' تنبيهًا.');

        return self::SUCCESS;
    }

    private function medicalFollowUps(NotificationService $notifications): int
    {
        $due = MedicalRecord::query()
            ->with('refugee')
            ->where('needs_follow_up', true)
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<=', today())
            ->get();

        foreach ($due as $record) {
            $overdue = $record->follow_up_date?->isBefore(today()) ?? false;

            $notifications->forRoles(
                ['medical_officer', 'admin'],
                $overdue ? 'medical_follow_up_overdue' : 'medical_follow_up_due',
                $overdue ? 'متابعة طبية متأخرة' : 'متابعة طبية مستحقة اليوم',
                'اللاجئ '.($record->refugee?->full_name ?? '—').' — تاريخ المتابعة '
                    .$record->follow_up_date?->format('Y-m-d').'.',
                $record
            );
        }

        return $due->count();
    }

    private function unhousedRefugees(NotificationService $notifications, int $days): int
    {
        $threshold = Carbon::today()->subDays(max(0, $days));

        $count = Refugee::query()
            ->where('status', 'active')
            ->where('housing_status', 'unassigned')
            ->where('created_at', '<=', $threshold)
            ->count();

        if ($count === 0) {
            return 0;
        }

        $notifications->forRoles(
            ['housing_officer', 'manager', 'admin'],
            'housing_backlog',
            'لاجئون بدون سكن منذ فترة',
            'يوجد '.$count.' لاجئًا بدون وحدة سكنية منذ أكثر من '.$days.' أيام.',
        );

        return 1;
    }

    private function longAbsences(NotificationService $notifications, int $days): int
    {
        $threshold = Carbon::today()->subDays(max(0, $days));

        // Someone recorded as "outside" whose last movement was an exit long ago
        // has not been logged back in through any checkpoint.
        $absent = Refugee::query()
            ->with('currentCamp')
            ->where('status', 'active')
            ->where('presence_status', 'outside')
            ->whereHas('entryExitLogs', function ($query) use ($threshold): void {
                $query->where('movement_type', 'exit')->where('movement_datetime', '<=', $threshold);
            })
            ->whereDoesntHave('entryExitLogs', function ($query) use ($threshold): void {
                $query->where('movement_datetime', '>', $threshold);
            })
            ->get();

        foreach ($absent as $refugee) {
            $notifications->forRoles(
                ['security_officer', 'manager', 'admin'],
                'long_absence',
                'غياب طويل عن المخيم',
                $refugee->full_name.' خارج المخيم منذ أكثر من '.$days.' أيام دون تسجيل عودة.',
                $refugee
            );
        }

        return $absent->count();
    }

    private function fullShelters(NotificationService $notifications): int
    {
        $full = Shelter::query()
            ->with('camp')
            ->where('status', 'active')
            ->withCount(['refugees as occupied' => fn ($query) => $query->where('status', 'active')])
            ->get()
            ->filter(fn (Shelter $shelter) => $shelter->occupied >= $shelter->capacity);

        foreach ($full as $shelter) {
            $notifications->forRoles(
                ['housing_officer', 'admin'],
                'shelter_full',
                'وحدة سكنية ممتلئة',
                'الوحدة '.$shelter->code.' في '.($shelter->camp?->name ?? '—').' وصلت إلى كامل السعة.',
                $shelter
            );
        }

        return $full->count();
    }
}
