<?php

namespace App\Jobs;

use App\Models\Player;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPlayerMinutes implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    protected string $season;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $season = null)
    {
        $this->season = $season ?? $this->getCurrentSeason();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $apiKey = config('services.sportsblaze.key');

        if (empty($apiKey)) {
            Log::error('SyncPlayerMinutes: SPORTSBLAZE_API_KEY is not configured');
            return;
        }

        $players = Player::whereNotNull('sportsblaze_player_id')->get();

        if ($players->isEmpty()) {
            Log::info('SyncPlayerMinutes: No players with SportsBlaze IDs to sync');
            return;
        }

        $playerIds = $players->pluck('sportsblaze_player_id')->implode(',');

        try {
            $response = Http::timeout(30)->get(
                "https://api.sportsblaze.com/nba/v1/splits/players/{$this->season}/regularseason.json",
                [
                    'key' => $apiKey,
                    'id' => $playerIds,
                ]
            );

            if (!$response->successful()) {
                Log::error('SyncPlayerMinutes: API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return;
            }

            $data = $response->json();
            $this->updatePlayerMinutes($data, $players);

        } catch (\Exception $e) {
            Log::error('SyncPlayerMinutes: Exception occurred', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update player minutes from API response.
     */
    protected function updatePlayerMinutes(array $data, $players): void
    {
        $apiPlayers = collect($data['players'] ?? []);
        $updated = 0;

        foreach ($players as $player) {
            $apiPlayer = $apiPlayers->firstWhere('id', $player->sportsblaze_player_id);

            if ($apiPlayer) {
                $totalMinutes = $apiPlayer['stats']['total']['minutes'] ?? null;
                $avgMinutes = $apiPlayer['stats']['average']['minutes'] ?? null;

                if ($totalMinutes !== null || $avgMinutes !== null) {
                    $player->update([
                        'minutes_played' => $totalMinutes !== null ? (int) $totalMinutes : null,
                        'average_minutes_played' => $avgMinutes !== null ? (float) $avgMinutes : null,
                        'stats_synced_at' => now(),
                    ]);
                    $updated++;
                }
            }
        }

        Log::info('SyncPlayerMinutes: Sync completed', [
            'season' => $this->season,
            'players_synced' => $updated,
            'total_players' => $players->count(),
        ]);
    }

    /**
     * Determine the current NBA season string.
     * NBA seasons span two years (e.g., 2024-25 season runs Oct 2024 - Jun 2025).
     */
    protected function getCurrentSeason(): string
    {
        $now = now();
        $year = $now->year;
        $month = $now->month;

        // NBA season starts in October. If we're before October, use previous year as start.
        if ($month < 10) {
            $startYear = $year - 1;
        } else {
            $startYear = $year;
        }

        $endYear = $startYear + 1;

        return "{$startYear}-" . substr((string) $endYear, -2);
    }
}
