<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('challenges:start-due')->everyMinute()->withoutOverlapping();
Schedule::command('checkins:schedule')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('checkins:expire')->everyMinute()->withoutOverlapping();
Schedule::command('challenges:settle')->hourly()->withoutOverlapping();
