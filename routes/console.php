<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about-spinellanka', function (): void {
    $this->components->info('Spinellanka application environment is ready.');
})->purpose('Verify the Spinellanka console entry point');

Schedule::command('availability:expire-holds')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('availability:cleanup-stale')
    ->daily()
    ->withoutOverlapping();
