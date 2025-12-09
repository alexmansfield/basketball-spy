<?php

namespace App\Jobs;

use App\Models\Team;
use App\Services\BallDontLieService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncTeamsFromBallDontLie implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 120;
    public int $timeout = 300;

    /**
     * Execute the job.
     */
    public function handle(BallDontLieService $api): void
    {
        Log::info('SyncTeamsFromBallDontLie: Starting team sync');

        $teams = $api->getTeams();

        if (empty($teams)) {
            Log::warning('SyncTeamsFromBallDontLie: No teams returned from API');
            return;
        }

        $stats = ['created' => 0, 'updated' => 0];

        foreach ($teams as $teamData) {
            $bdlId = $teamData['id'] ?? null;
            if (!$bdlId) {
                continue;
            }

            // Find team by BallDontLie ID or abbreviation
            $team = Team::where('balldontlie_id', $bdlId)
                ->orWhere('abbreviation', strtoupper($teamData['abbreviation'] ?? ''))
                ->first();

            $teamAttributes = [
                'balldontlie_id' => $bdlId,
                'name' => $teamData['full_name'] ?? $teamData['name'] ?? '',
                'abbreviation' => strtoupper($teamData['abbreviation'] ?? ''),
                'location' => $teamData['city'] ?? '',
                'nickname' => $teamData['name'] ?? '',
                'league' => 'NBA',
                'extra_attributes' => [
                    'conference' => $teamData['conference'] ?? null,
                    'division' => $teamData['division'] ?? null,
                ],
            ];

            if ($team) {
                // Update existing team
                $team->update($teamAttributes);
                $stats['updated']++;
            } else {
                // Create new team
                Team::create($teamAttributes);
                $stats['created']++;
            }
        }

        Log::info('SyncTeamsFromBallDontLie: Sync completed', [
            'teams_processed' => count($teams),
            'created' => $stats['created'],
            'updated' => $stats['updated'],
        ]);
    }
}
