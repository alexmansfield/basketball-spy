<?php

namespace App\Jobs;

use App\Models\Game;
use App\Models\Team;
use App\Services\BallDontLieService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncGamesFromBallDontLie implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 120;
    public int $timeout = 600; // 10 minutes

    protected int $days;

    /**
     * Create a new job instance.
     *
     * @param int $days Number of days to fetch (forward and backward)
     */
    public function __construct(int $days = 7)
    {
        $this->days = $days;
    }

    /**
     * Execute the job.
     */
    public function handle(BallDontLieService $api): void
    {
        Log::info('SyncGamesFromBallDontLie: Starting game sync', ['days' => $this->days]);

        // Build team lookup by BallDontLie ID
        $teamsByBdlId = Team::whereNotNull('balldontlie_id')
            ->get()
            ->keyBy('balldontlie_id');

        if ($teamsByBdlId->isEmpty()) {
            Log::warning('SyncGamesFromBallDontLie: No teams with balldontlie_id found. Run SyncTeamsFromBallDontLie first.');
            return;
        }

        // Calculate date range
        $startDate = now()->subDays(1)->format('Y-m-d'); // Include yesterday for final scores
        $endDate = now()->addDays($this->days)->format('Y-m-d');

        Log::info('SyncGamesFromBallDontLie: Fetching games', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'total' => 0];
        $processedDates = [];

        foreach ($api->getAllGamesForDateRange($startDate, $endDate) as $gameData) {
            $stats['total']++;
            $bdlId = $gameData['id'] ?? null;

            if (!$bdlId) {
                $stats['skipped']++;
                continue;
            }

            // Get teams
            $homeTeamBdlId = $gameData['home_team']['id'] ?? null;
            $awayTeamBdlId = $gameData['visitor_team']['id'] ?? null;

            $homeTeam = $homeTeamBdlId ? $teamsByBdlId->get($homeTeamBdlId) : null;
            $awayTeam = $awayTeamBdlId ? $teamsByBdlId->get($awayTeamBdlId) : null;

            if (!$homeTeam || !$awayTeam) {
                Log::debug('SyncGamesFromBallDontLie: Game has unknown teams', [
                    'game_id' => $bdlId,
                    'home_bdl_id' => $homeTeamBdlId,
                    'away_bdl_id' => $awayTeamBdlId,
                ]);
                $stats['skipped']++;
                continue;
            }

            // Parse game date/time
            $gameDate = $gameData['date'] ?? null;
            $scheduledAt = $gameDate ? Carbon::parse($gameDate) : now();

            // Track dates for cache clearing
            $processedDates[$scheduledAt->format('Y-m-d')] = true;

            // Map status
            $status = $this->mapStatus($gameData['status'] ?? 'scheduled');

            $gameAttributes = [
                'balldontlie_id' => $bdlId,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'scheduled_at' => $scheduledAt,
                'status' => $status,
                'home_team_score' => $gameData['home_team_score'] ?? null,
                'away_team_score' => $gameData['visitor_team_score'] ?? null,
                'period' => $gameData['period'] ?? null,
                'time' => $gameData['time'] ?? null,
                'postseason' => $gameData['postseason'] ?? false,
                'season' => $gameData['season'] ?? null,
                'external_id' => "bdl-{$bdlId}",
                'extra_attributes' => [
                    'home_team_q1' => $gameData['home_team_q1'] ?? null,
                    'home_team_q2' => $gameData['home_team_q2'] ?? null,
                    'home_team_q3' => $gameData['home_team_q3'] ?? null,
                    'home_team_q4' => $gameData['home_team_q4'] ?? null,
                    'home_team_ot' => $gameData['home_team_ot'] ?? null,
                    'visitor_team_q1' => $gameData['visitor_team_q1'] ?? null,
                    'visitor_team_q2' => $gameData['visitor_team_q2'] ?? null,
                    'visitor_team_q3' => $gameData['visitor_team_q3'] ?? null,
                    'visitor_team_q4' => $gameData['visitor_team_q4'] ?? null,
                    'visitor_team_ot' => $gameData['visitor_team_ot'] ?? null,
                ],
            ];

            // Find existing game by BallDontLie ID
            $game = Game::where('balldontlie_id', $bdlId)->first();

            if ($game) {
                $game->update($gameAttributes);
                $stats['updated']++;
            } else {
                Game::create($gameAttributes);
                $stats['created']++;
            }
        }

        // Clear cache for processed dates
        foreach (array_keys($processedDates) as $date) {
            Cache::forget("games:date:{$date}");
        }

        Log::info('SyncGamesFromBallDontLie: Sync completed', $stats);
    }

    /**
     * Map BallDontLie status to our status values.
     */
    protected function mapStatus(string $apiStatus): string
    {
        // BallDontLie uses: "Final", "1st Qtr", "2nd Qtr", "Halftime", "3rd Qtr", "4th Qtr", "In Progress"
        $status = strtolower($apiStatus);

        return match (true) {
            str_contains($status, 'final') => 'final',
            str_contains($status, 'qtr') || str_contains($status, 'progress') || str_contains($status, 'ot') => 'live',
            str_contains($status, 'halftime') || str_contains($status, 'half') => 'live',
            default => 'scheduled',
        };
    }
}
