<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Telegram numbers: a small batch every hour reads each channel about once a day,
// and the history is compacted monthly (see the commands for details).
// Needs the server cron: * * * * * php artisan schedule:run
Schedule::command('telegram:refresh')->hourly()->withoutOverlapping();
Schedule::command('telegram:compact')->monthlyOn(1, '03:30');
