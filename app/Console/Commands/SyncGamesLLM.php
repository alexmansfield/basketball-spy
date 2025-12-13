<?php

namespace App\Console\Commands;

use App\Services\NBAScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncGamesLLM extends Command
{
    protected $signature = 'app:sync-games-llm
                            {--days=3 : Number of days to fetch (default: 3)}
                            {--date= : Specific date to fetch (YYYY-MM-DD)}';

    protected $description = 'Sync NBA games using OpenAI';

    public function handle(NBAScheduleService $service): int
    {
        $openaiKey = config('services.openai.key');

        if (empty($openaiKey)) {
            $this->error('OPENAI_API_KEY is not configured!');
            return Command::FAILURE;
        }

        $this->info('✓ OPENAI_API_KEY configured');
        $this->newLine();

        // Single date mode
        if ($specificDate = $this->option('date')) {
            return $this->syncDate($service, $specificDate);
        }

        // Multi-day mode
        $days = (int) $this->option('days');
        $this->info("Syncing games for today + next {$days} days...");
        $this->newLine();

        $totalStored = 0;

        for ($i = 0; $i <= $days; $i++) {
            $date = now()->addDays($i)->format('Y-m-d');
            $result = $this->syncDate($service, $date, false);
            if ($result > 0) {
                $totalStored += $result;
            }
        }

        $this->newLine();
        $this->info("✓ Total games synced: {$totalStored}");

        return Command::SUCCESS;
    }

    protected function syncDate(NBAScheduleService $service, string $date, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("Fetching games for {$date}...");
        } else {
            $this->line("  {$date}: ", false);
        }

        try {
            $games = $service->fetchGamesForDate($date);

            if (empty($games)) {
                if ($verbose) {
                    $this->warn("No games found for {$date}");
                } else {
                    $this->line('no games');
                }
                return 0;
            }

            $stored = $service->storeGames($games);
            Cache::forget("games:date:{$date}");

            if ($verbose) {
                $this->info("✓ Stored {$stored} games for {$date}");
                foreach ($games as $game) {
                    $home = \App\Models\Team::find($game['home_team_id']);
                    $away = \App\Models\Team::find($game['away_team_id']);
                    $time = \Illuminate\Support\Carbon::parse($game['scheduled_at'])->setTimezone('America/New_York')->format('g:i A');
                    $this->line("  • {$away->abbreviation} @ {$home->abbreviation} - {$time} ET");
                }
            } else {
                $this->line("{$stored} games");
            }

            return $stored;

        } catch (\Exception $e) {
            if ($verbose) {
                $this->error("Failed: {$e->getMessage()}");
            } else {
                $this->line("<error>error</error>");
            }
            return -1;
        }
    }
}
