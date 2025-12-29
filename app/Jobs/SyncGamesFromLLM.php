<?php

namespace App\Jobs;

use App\Services\NBAScheduleService;
use App\Services\SlackAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncGamesFromLLM implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 120;
    public int $timeout = 420; // 7 minutes - polling can take up to 5 min

    protected int $days;

    public function __construct(int $days = 3)
    {
        $this->days = $days;
    }

    public function handle(NBAScheduleService $service): void
    {
        $date = now()->format('Y-m-d');
        Log::info('SyncGamesFromLLM: Starting', ['date' => $date]);

        // Saved prompt returns 7 days at once - only need one API call
        $games = $service->fetchGamesForDate($date);

        if (empty($games)) {
            // During NBA season (Oct-Apr), empty results are suspicious
            $month = (int) now()->format('n');
            $isNBASeason = $month >= 10 || $month <= 4;

            if ($isNBASeason) {
                Log::error('SyncGamesFromLLM: No games returned during NBA season - likely API issue', [
                    'date' => $date,
                    'month' => $month,
                ]);
                // Throw to trigger job retry
                throw new \RuntimeException('No games returned during NBA season - API may have failed');
            } else {
                Log::info('SyncGamesFromLLM: No games returned (off-season)', ['date' => $date]);
            }
            return;
        }

        $stored = $service->storeGames($games);

        // Clear cache for all dates in the response
        $dates = collect($games)->pluck('scheduled_at')->map(fn($dt) => $dt->format('Y-m-d'))->unique();
        foreach ($dates as $date) {
            Cache::forget("games:date:{$date}");
        }

        Log::info('SyncGamesFromLLM: Completed successfully', [
            'games_stored' => $stored,
            'dates_covered' => $dates->toArray(),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::critical('SyncGamesFromLLM: All retries exhausted - MANUAL INTERVENTION REQUIRED', [
            'error' => $exception?->getMessage(),
            'date' => now()->format('Y-m-d'),
        ]);

        app(SlackAlertService::class)->syncFailure(
            'SyncGamesFromLLM',
            $exception?->getMessage(),
            ['Date' => now()->format('Y-m-d')]
        );
    }
}
