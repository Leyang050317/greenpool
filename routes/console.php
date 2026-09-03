<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('trips:expire-departed')->everyMinute()->withoutOverlapping();
Schedule::command('trips:send-reminders')->everyMinute()->withoutOverlapping();
