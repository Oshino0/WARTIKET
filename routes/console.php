<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Return stock held by unpaid online-payment orders once their deadline passes.
// Needs `php artisan schedule:work` (development) or a cron job running `schedule:run` (production).
Schedule::command('jualtiket:release-expired')->everyMinute();
