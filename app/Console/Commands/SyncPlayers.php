<?php

namespace App\Console\Commands;

use App\Jobs\SyncPlayersFromBallDontLie;
use App\Models\Player;
use App\Models\Team;
use App\Services\BallDontLieService;
use Illuminate\Console\Command;

class SyncPlayers extends Command
{
    protected $signature = 'app:sync-players
                            {--queue : Dispatch job to queue instead of running synchronously}';

    protected $description = 'Sync NBA players from BallDontLie API';

    public function handle(BallDontLieService $api): int
    {
        $useQueue = $this->option('queue');

        $this->info('Syncing NBA players from BallDontLie API...');

        // Check API key
        $apiKey = config('services.balldontlie.key');
        if (empty($apiKey)) {
            $this->error('BALL_DONT_LIE_API_KEY is not configured!');
            return Command::FAILURE;
        }
        $this->info('✓ BALL_DONT_LIE_API_KEY is configured');

        // Check for teams
        $teamsByBdlId = Team::whereNotNull('balldontlie_id')
            ->get()
            ->keyBy('balldontlie_id');

        if ($teamsByBdlId->isEmpty()) {
            $this->error('No teams with balldontlie_id found. Run `php artisan app:sync-teams` first.');
            return Command::FAILURE;
        }
        $this->info("✓ Found " . $teamsByBdlId->count() . " teams with BallDontLie IDs");

        if ($useQueue) {
            SyncPlayersFromBallDontLie::dispatch();
            $this->info('Job dispatched to queue. Run `php artisan queue:work` to process.');
            return Command::SUCCESS;
        }

        $this->info('Running synchronously (this may take several minutes due to rate limiting)...');
        $this->warn('Rate limit: 5 requests per minute. Please be patient.');

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'total' => 0];

        foreach ($api->getAllPlayers() as $playerData) {
            $stats['total']++;
            $bdlId = $playerData['id'] ?? null;

            if (!$bdlId) {
                $stats['skipped']++;
                continue;
            }

            $teamBdlId = $playerData['team']['id'] ?? null;
            $team = $teamBdlId ? $teamsByBdlId->get($teamBdlId) : null;

            if (!$team) {
                $stats['skipped']++;
                continue;
            }

            $height = $playerData['height'] ?? null;
            $weight = isset($playerData['weight']) ? $playerData['weight'] . ' lbs' : null;

            $playerAttributes = [
                'balldontlie_id' => $bdlId,
                'team_id' => $team->id,
                'name' => trim(($playerData['first_name'] ?? '') . ' ' . ($playerData['last_name'] ?? '')),
                'jersey' => $playerData['jersey_number'] ?? '',
                'position' => $playerData['position'] ?? '',
                'height' => $height,
                'weight' => $weight,
                'extra_attributes' => [
                    'first_name' => $playerData['first_name'] ?? null,
                    'last_name' => $playerData['last_name'] ?? null,
                    'college' => $playerData['college'] ?? null,
                    'country' => $playerData['country'] ?? null,
                    'draft_year' => $playerData['draft_year'] ?? null,
                    'draft_round' => $playerData['draft_round'] ?? null,
                    'draft_number' => $playerData['draft_number'] ?? null,
                ],
            ];

            $player = Player::where('balldontlie_id', $bdlId)->first();

            if ($player) {
                $player->update($playerAttributes);
                $stats['updated']++;
            } else {
                Player::create($playerAttributes);
                $stats['created']++;
            }

            // Show progress every 50 players
            if ($stats['total'] % 50 === 0) {
                $this->line("  Processed {$stats['total']} players...");
            }
        }

        $this->newLine();
        $this->info("✓ Players synced: {$stats['created']} created, {$stats['updated']} updated, {$stats['skipped']} skipped");
        $this->info("Total players in database: " . Player::count());

        return Command::SUCCESS;
    }
}
