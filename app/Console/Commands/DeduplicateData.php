<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deduplicate-data {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate teams and players, keeping the one with most data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN - No data will be deleted');
        }

        $this->deduplicateTeams($dryRun);
        $this->deduplicatePlayers($dryRun);

        $this->info('Deduplication complete!');

        return Command::SUCCESS;
    }

    /**
     * Deduplicate teams by abbreviation, keeping the one with the most players.
     */
    protected function deduplicateTeams(bool $dryRun): void
    {
        $this->info('Checking for duplicate teams...');

        // Find duplicate teams by abbreviation
        $duplicates = Team::withTrashed()
            ->select('abbreviation', DB::raw('COUNT(*) as count'))
            ->groupBy('abbreviation')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate teams found.');
            return;
        }

        $this->warn("Found {$duplicates->count()} abbreviations with duplicates");

        foreach ($duplicates as $dup) {
            $teams = Team::withTrashed()
                ->where('abbreviation', $dup->abbreviation)
                ->withCount('players')
                ->orderByDesc('players_count')
                ->orderBy('id') // Keep oldest if tied
                ->get();

            $keeper = $teams->first();
            $toDelete = $teams->slice(1);

            $this->line("  {$dup->abbreviation}: keeping ID {$keeper->id} ({$keeper->players_count} players), deleting " . $toDelete->pluck('id')->implode(', '));

            if (!$dryRun) {
                foreach ($toDelete as $team) {
                    // Reassign any players to the keeper team
                    Player::where('team_id', $team->id)->update(['team_id' => $keeper->id]);

                    // Force delete the duplicate
                    $team->forceDelete();
                }
            }
        }

        $deletedCount = $dryRun ? 0 : $duplicates->sum('count') - $duplicates->count();
        $this->info("Teams: Would delete {$deletedCount} duplicates");
    }

    /**
     * Deduplicate players by name + team_id, keeping the one with most data.
     */
    protected function deduplicatePlayers(bool $dryRun): void
    {
        $this->info('Checking for duplicate players...');

        // Find duplicate players by name within the same team
        $duplicates = Player::withTrashed()
            ->select('name', 'team_id', DB::raw('COUNT(*) as count'))
            ->groupBy('name', 'team_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate players found.');
            return;
        }

        $this->warn("Found {$duplicates->count()} player name/team combinations with duplicates");

        $totalDeleted = 0;

        foreach ($duplicates as $dup) {
            $players = Player::withTrashed()
                ->where('name', $dup->name)
                ->where('team_id', $dup->team_id)
                ->orderByRaw('CASE WHEN minutes_played IS NOT NULL THEN 0 ELSE 1 END') // Prefer with stats
                ->orderByDesc('minutes_played')
                ->orderBy('id') // Keep oldest if tied
                ->get();

            $keeper = $players->first();
            $toDelete = $players->slice(1);

            $this->line("  {$dup->name} (team {$dup->team_id}): keeping ID {$keeper->id}, deleting " . $toDelete->pluck('id')->implode(', '));

            if (!$dryRun) {
                foreach ($toDelete as $player) {
                    $player->forceDelete();
                }
            }

            $totalDeleted += $toDelete->count();
        }

        $this->info("Players: " . ($dryRun ? "Would delete" : "Deleted") . " {$totalDeleted} duplicates");
    }
}
