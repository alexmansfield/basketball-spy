<?php

use App\Jobs\SyncPlayerMinutes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncPlayerMinutes)
    ->daily()
    ->at('05:00')
    ->withoutOverlapping()
    ->onOneServer();
