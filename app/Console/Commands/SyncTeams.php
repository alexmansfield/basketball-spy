<?php

namespace App\Console\Commands;

use App\Jobs\SyncTeamsFromBallDontLie;
use App\Models\Team;
use App\Services\BallDontLieService;
use Illuminate\Console\Command;

class SyncTeams extends Command
{
    protected $signature = 'app:sync-teams
                            {--queue : Dispatch job to queue instead of running synchronously}';

    protected $description = 'Sync NBA teams from BallDontLie API';

    public function handle(BallDontLieService $api): int
    {
        $useQueue = $this->option('queue');

        $this->info('Syncing NBA teams from BallDontLie API...');

        // Check API key
        $apiKey = config('services.balldontlie.key');
        if (empty($apiKey)) {
            $this->error('BALL_DONT_LIE_API_KEY is not configured!');
            return Command::FAILURE;
        }
        $this->info('✓ BALL_DONT_LIE_API_KEY is configured');

        if ($useQueue) {
            SyncTeamsFromBallDontLie::dispatch();
            $this->info('Job dispatched to queue. Run `php artisan queue:work` to process.');
            return Command::SUCCESS;
        }

        $this->info('Running synchronously...');

        $teams = $api->getTeams();

        if (empty($teams)) {
            $this->error('No teams returned from API');
            return Command::FAILURE;
        }

        $this->info("✓ Received " . count($teams) . " teams from API");

        $bar = $this->output->createProgressBar(count($teams));
        $bar->start();

        $stats = ['created' => 0, 'updated' => 0];

        foreach ($teams as $teamData) {
            $bdlId = $teamData['id'] ?? null;
            if (!$bdlId) {
                $bar->advance();
                continue;
            }

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
                $team->update($teamAttributes);
                $stats['updated']++;
            } else {
                Team::create($teamAttributes);
                $stats['created']++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Teams synced: {$stats['created']} created, {$stats['updated']} updated");
        $this->info("Total teams in database: " . Team::count());

        return Command::SUCCESS;
    }
}
