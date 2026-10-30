<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The RDW data is refreshed daily; once a week is enough for us.
Schedule::command('rdw:update')->weeklyOn(1, '03:00')->withoutOverlapping();

// Daily APK reminders (and clean up unconfirmed requests)
Schedule::command('apk:remind')->dailyAt('08:00')->withoutOverlapping();

// A copy of the database every night, on the live server only
Schedule::command('backup:database')->dailyAt('02:30')->environments(['production']);

// The database cache keeps expired rows (car data, rate limits with an IP address) until the same key is asked for again
Schedule::command('cache:clean-expired')->hourly();
