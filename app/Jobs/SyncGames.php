<?php

namespace App\Jobs;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncGames implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    protected string $date;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $date = null)
    {
        $this->date = $date ?? now()->toDateString();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $apiKey = config('services.sportsblaze.key');

        if (empty($apiKey)) {
            Log::error('SyncGames: SPORTSBLAZE_API_KEY is not configured');
            return;
        }

        try {
            // SportsBlaze schedule endpoint
            $response = Http::timeout(30)->get(
                "https://api.sportsblaze.com/nba/v1/games/{$this->date}/schedule.json",
                [
                    'key' => $apiKey,
                ]
            );

            if (!$response->successful()) {
                Log::error('SyncGames: API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'date' => $this->date,
                ]);
                return;
            }

            $data = $response->json();
            $this->processGames($data);

            // Clear cache for this date
            Cache::forget("games:date:{$this->date}");
            if ($this->date === now()->toDateString()) {
                Cache::forget("games:today:{$this->date}");
            }

        } catch (\Exception $e) {
            Log::error('SyncGames: Exception occurred', [
                'message' => $e->getMessage(),
                'date' => $this->date,
            ]);
            throw $e;
        }
    }

    /**
     * Process and store games from API response.
     */
    protected function processGames(array $data): void
    {
        $games = $data['games'] ?? [];
        $synced = 0;
        $skipped = 0;

        // Build team lookup by abbreviation (lowercase for case-insensitive matching)
        $teams = Team::all()->keyBy(fn($t) => strtolower($t->abbreviation));

        foreach ($games as $gameData) {
            $externalId = $gameData['id'] ?? null;
            if (!$externalId) {
                $skipped++;
                continue;
            }

            // Find teams by abbreviation
            $homeAbbr = strtolower($gameData['home']['alias'] ?? $gameData['home']['abbreviation'] ?? '');
            $awayAbbr = strtolower($gameData['away']['alias'] ?? $gameData['away']['abbreviation'] ?? '');

            $homeTeam = $teams->get($homeAbbr);
            $awayTeam = $teams->get($awayAbbr);

            if (!$homeTeam || !$awayTeam) {
                Log::warning('SyncGames: Could not find teams', [
                    'home_abbr' => $homeAbbr,
                    'away_abbr' => $awayAbbr,
                    'external_id' => $externalId,
                ]);
                $skipped++;
                continue;
            }

            // Parse scheduled time
            $scheduledAt = $this->parseScheduledAt($gameData);

            // Upsert game
            Game::updateOrCreate(
                ['external_id' => $externalId],
                [
                    'home_team_id' => $homeTeam->id,
                    'away_team_id' => $awayTeam->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => $this->mapStatus($gameData['status'] ?? 'scheduled'),
                ]
            );

            $synced++;
        }

        Log::info('SyncGames: Sync completed', [
            'date' => $this->date,
            'games_synced' => $synced,
            'games_skipped' => $skipped,
        ]);
    }

    /**
     * Parse scheduled time from API response.
     */
    protected function parseScheduledAt(array $gameData): Carbon
    {
        // SportsBlaze typically returns ISO 8601 format
        if (isset($gameData['scheduled'])) {
            return Carbon::parse($gameData['scheduled']);
        }

        // Fallback: combine date and time
        $date = $gameData['date'] ?? $this->date;
        $time = $gameData['time'] ?? '19:00:00';

        return Carbon::parse("{$date} {$time}");
    }

    /**
     * Map SportsBlaze status to our status values.
     */
    protected function mapStatus(string $apiStatus): string
    {
        return match (strtolower($apiStatus)) {
            'scheduled', 'created' => 'scheduled',
            'inprogress', 'in_progress', 'live' => 'live',
            'halftime' => 'halftime',
            'complete', 'closed', 'final' => 'final',
            'postponed' => 'postponed',
            'cancelled', 'canceled' => 'cancelled',
            default => 'scheduled',
        };
    }
}
