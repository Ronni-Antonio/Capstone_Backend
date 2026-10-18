<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retrain the saved Prophet models once per week using the latest RDS history.
// config/app.php uses Asia/Manila, so this runs Sunday at 2:00 AM Philippine time.
Schedule::command('prophet:train --forecast-days=30')
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping();

// Operational alerts are generated only when the corresponding admin
// preference is enabled. The command uses Controller 1's last_seen_at heartbeat
// for offline detection and the Smart Bin maintenance date for reminders.
Schedule::command('notifications:monitor')
    ->everyFiveMinutes()
    ->withoutOverlapping();
