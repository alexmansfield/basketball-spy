<?php

namespace App\Jobs;

use App\Services\NBAScheduleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncGamesFromLLM implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 120;

    protected int $days;

    public function __construct(int $days = 3)
    {
        $this->days = $days;
    }

    public function handle(NBAScheduleService $service): void
    {
        Log::info('SyncGamesFromLLM: Starting', ['days' => $this->days]);

        $stored = 0;
        $errors = 0;

        // Sync today + next N days
        for ($i = 0; $i <= $this->days; $i++) {
            $date = now()->addDays($i)->format('Y-m-d');

            try {
                $games = $service->fetchGamesForDate($date);

                if (!empty($games)) {
                    $count = $service->storeGames($games);
                    $stored += $count;
                    Cache::forget("games:date:{$date}");
                    Log::info("SyncGamesFromLLM: Stored {$count} games for {$date}");
                } else {
                    Log::info("SyncGamesFromLLM: No games for {$date}");
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error("SyncGamesFromLLM: Failed for {$date}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SyncGamesFromLLM: Completed', [
            'days_synced' => $this->days + 1,
            'games_stored' => $stored,
            'errors' => $errors,
        ]);
    }
}
