<?php

use App\Console\Commands\PruneAuditLogs;
use App\Console\Commands\SendDailyDigest;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about:camps', function (): void {
    /** @var ClosureCommand $this */
    $this->info('Local Refugee Camp Management System');
})->purpose('Show project name');

// Early enough that officers see the day's follow-ups when they arrive.
Schedule::command(SendDailyDigest::class)
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(PruneAuditLogs::class)
    ->weeklyOn(5, '02:00')
    ->withoutOverlapping()
    ->onOneServer();
