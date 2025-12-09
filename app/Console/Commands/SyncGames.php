<?php

namespace App\Console\Commands;

use App\Jobs\SyncGames as SyncGamesJob;
use Illuminate\Console\Command;

class SyncGames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-games
                            {date? : The date to sync games for (YYYY-MM-DD format, defaults to today)}
                            {--days=7 : Number of days to sync (from the start date)}
                            {--queue : Dispatch jobs to queue instead of running synchronously}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync games from SportsBlaze API for a specific date or range';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = $this->argument('date') ?? now()->toDateString();
        $days = (int) $this->option('days');
        $useQueue = $this->option('queue');

        $this->info("Syncing games from {$startDate} for {$days} day(s)...");

        $dates = collect();
        $current = \Carbon\Carbon::parse($startDate);

        for ($i = 0; $i < $days; $i++) {
            $dates->push($current->copy()->addDays($i)->toDateString());
        }

        $bar = $this->output->createProgressBar($dates->count());
        $bar->start();

        foreach ($dates as $date) {
            if ($useQueue) {
                SyncGamesJob::dispatch($date);
                $this->line(" Queued: {$date}");
            } else {
                try {
                    $job = new SyncGamesJob($date);
                    $job->handle();
                    $this->line(" Synced: {$date}");
                } catch (\Exception $e) {
                    $this->error(" Failed: {$date} - {$e->getMessage()}");
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($useQueue) {
            $this->info("Jobs dispatched to queue. Run `php artisan queue:work` to process them.");
        } else {
            $this->info("Sync complete!");
        }

        return Command::SUCCESS;
    }
}
