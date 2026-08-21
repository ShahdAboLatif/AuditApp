<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cleaning Chart uses compute-on-read (no window generation / auto-fail crons).
// (Optional) reminders for due-soon / missed tasks can be added here later:
// Schedule::command('cleaning:send-due-reminders')->everyFifteenMinutes();

// Deliver queued qa.v1.* events (belt-and-suspenders alongside the --forever worker).
Schedule::command('outbox:publish')->everyMinute()->withoutOverlapping();
