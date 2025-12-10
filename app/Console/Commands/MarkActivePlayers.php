<?php

namespace App\Console\Commands;

use App\Models\Player;
use Illuminate\Console\Command;

class MarkActivePlayers extends Command
{
    protected $signature = 'app:mark-active-players';

    protected $description = 'Mark players as active based on having an NBA player ID (from headshot sync)';

    public function handle(): int
    {
        $this->info('Marking active players based on NBA player ID...');

        // Reset all to inactive
        $totalPlayers = Player::count();
        Player::query()->update(['is_active' => false]);
        $this->info("Reset {$totalPlayers} players to inactive");

        // Mark players with nba_player_id as active
        // These are players matched from NBA Stats API during headshot sync
        $updated = Player::whereNotNull('nba_player_id')
            ->update(['is_active' => true]);

        $this->info("✓ Marked {$updated} players as active (have NBA player ID)");

        // Show stats
        $activeCount = Player::where('is_active', true)->count();
        $inactiveCount = Player::where('is_active', false)->count();

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Active players: {$activeCount}");
        $this->info("  Inactive players: {$inactiveCount}");

        return Command::SUCCESS;
    }
}
