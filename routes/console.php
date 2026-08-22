<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Artisan;

Artisan::command('about:camps', function (): void {
    /** @var ClosureCommand $this */
    $this->info('Local Refugee Camp Management System');
})->purpose('Show project name');
