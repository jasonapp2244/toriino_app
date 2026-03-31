<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\AutoCompleteExpiredSessions;
use App\Console\Commands\SendSessionReminders;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-complete expired/abandoned sessions every 5 minutes.
// Run: php artisan schedule:run  (add to cron: * * * * * php artisan schedule:run)
// Send 5-minute session reminders every minute
Schedule::command(SendSessionReminders::class, ['--minutes=5', '--window=2'])
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Auto-complete expired/abandoned sessions every 5 minutes
Schedule::command(AutoCompleteExpiredSessions::class, ['--grace=10'])
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
