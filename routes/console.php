<?php

use App\Jobs\SyncGamesFromLLM;
use App\Jobs\SyncPlayerMinutes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync games every 2 hours (today + next 3 days via LLM)
Schedule::job(new SyncGamesFromLLM(3))
    ->everyTwoHours()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new SyncPlayerMinutes)
    ->daily()
    ->at('05:00')
    ->withoutOverlapping()
    ->onOneServer();
