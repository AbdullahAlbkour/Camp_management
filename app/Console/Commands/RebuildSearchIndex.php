<?php

namespace App\Console\Commands;

use App\Support\RefugeeSearchIndex;
use Illuminate\Console\Command;

class RebuildSearchIndex extends Command
{
    protected $signature = 'camps:rebuild-search {--check : Only report how many records are missing an index}';

    protected $description = 'Rebuild the Arabic search index for refugees after a bulk import or seed';

    public function handle(): int
    {
        $missing = RefugeeSearchIndex::missingCount();

        if ($this->option('check')) {
            $this->info($missing === 0
                ? 'فهرس البحث مكتمل.'
                : 'يوجد '.$missing.' سجلًا بلا فهرس بحث. شغّل الأمر دون --check لإعادة بنائه.');

            return $missing === 0 ? self::SUCCESS : self::FAILURE;
        }

        $bar = $this->output->createProgressBar();
        $bar->start();

        $count = RefugeeSearchIndex::rebuild(fn (int $done) => $bar->setProgress($done));

        $bar->finish();
        $this->newLine(2);
        $this->info('تمت إعادة بناء فهرس البحث لـ '.$count.' سجلًا.');

        return self::SUCCESS;
    }
}
