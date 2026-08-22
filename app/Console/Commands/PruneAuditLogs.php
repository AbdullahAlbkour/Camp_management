<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Retention for the audit trail.
 *
 * Routine low-sensitivity noise (logins, logouts, listing views) is pruned after
 * the retention window. High and critical entries — anything touching personal,
 * medical or security data, exports, user administration — are never pruned by
 * this command; removing those would defeat the point of keeping a trail.
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'camps:prune-audit-logs
                            {--days=180 : Keep low-sensitivity entries for this many days}
                            {--dry-run : Report what would be removed without deleting}';

    protected $description = 'Prune low-sensitivity audit entries past the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = Carbon::now()->subDays($days);

        $query = AuditLog::query()
            ->whereIn('sensitivity', ['low', 'medium'])
            ->where('created_at', '<', $cutoff);

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info('سيتم حذف '.$count.' سجل تدقيق أقدم من '.$cutoff->format('Y-m-d').'.');

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info('تم حذف '.$deleted.' سجل تدقيق منخفض الحساسية أقدم من '.$cutoff->format('Y-m-d').'.');
        $this->line('السجلات عالية الحساسية والحرجة محفوظة دائمًا.');

        return self::SUCCESS;
    }
}
