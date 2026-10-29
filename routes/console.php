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

// Announcements scheduled from Admin → الإعلانات go out at their time
Schedule::call(fn () => \App\Support\AnnouncementSender::sendDue())->everyMinute()->name('announcements:send-due')->withoutOverlapping();

// Member notifications (email and Telegram) wait in the database queue; this sends them each minute
// without a separate worker process. On a VPS a supervised "queue:work" can replace it.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')->everyMinute()->withoutOverlapping()->runInBackground();

// Throwaway email domains refused at sign-up (Admin → حماية التسجيل)
Artisan::command('signup:update-disposable', function () {
    $this->info(number_format(\App\Support\SignupGuard::updateDisposableList()).' disposable domains saved.');
})->purpose('Download the latest list of throwaway email domains');
Schedule::command('signup:update-disposable')->weeklyOn(1, '04:10');
