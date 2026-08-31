<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retrain the saved Prophet models once per week using the latest RDS history.
// config/app.php uses Asia/Manila, so this runs Sunday at 2:00 AM Philippine time.
Schedule::command('prophet:train')
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping();
