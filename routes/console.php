<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about-spinellanka', function (): void {
    $this->components->info('Spinellanka application environment is ready.');
})->purpose('Verify the Spinellanka console entry point');
