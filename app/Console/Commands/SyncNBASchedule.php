<?php

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncNBASchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-nba-schedule
                            {--days=7 : Number of days to fetch (default: 7)}
                            {--queue : Dispatch job to queue instead of running synchronously}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync NBA schedule from OpenAI with web search for the next N days';

    /**
     * OpenAI Responses API prompt ID for NBA schedule.
     */
    private const PROMPT_ID = 'pmpt_69389a8d44cc81938188f27bcdcf0df606e9bff2d576d7ec';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $useQueue = $this->option('queue');

        $this->info("Syncing NBA schedule for the next {$days} day(s)...");

        // Check API key first
        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            $this->error('OPENAI_API_KEY is not configured!');
            $this->error('Please add OPENAI_API_KEY to your environment variables.');
            return Command::FAILURE;
        }
        $this->info('✓ OPENAI_API_KEY is configured');

        if ($useQueue) {
            \App\Jobs\SyncNBASchedule::dispatch($days);
            $this->info('Job dispatched to queue. Run `php artisan queue:work` to process.');
            return Command::SUCCESS;
        }

        $this->info('Running synchronously (this may take 70-90 seconds)...');

        // Build the date range
        $startDate = now();
        $endDate = now()->addDays($days - 1);
        $dateRange = $startDate->format('F j, Y') . ' to ' . $endDate->format('F j, Y');

        $this->info("Date range: {$dateRange}");

        // Call OpenAI Responses API
        $this->info('Calling OpenAI Responses API with web search...');

        try {
            $response = Http::timeout(180)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/responses', [
                    'model' => 'gpt-4o-mini',
                    'input' => [
                        [
                            'role' => 'user',
                            'content' => "Get the NBA schedule from {$dateRange}. Today is " . now()->format('F j, Y') . ".",
                        ]
                    ],
                    'instructions' => self::PROMPT_ID,
                    'tools' => [
                        ['type' => 'web_search_preview']
                    ],
                ]);

            if (!$response->successful()) {
                $this->error("API request failed with status: {$response->status()}");
                $this->error("Response: {$response->body()}");
                return Command::FAILURE;
            }

            $this->info('✓ API response received');

            $data = $response->json();
            $content = $this->extractContent($data);

            if (empty($content)) {
                $this->error('Empty response from OpenAI');
                $this->error('Raw response: ' . json_encode($data, JSON_PRETTY_PRINT));
                return Command::FAILURE;
            }

            $this->info('✓ Content extracted (' . strlen($content) . ' characters)');
            $this->line('Preview: ' . substr($content, 0, 200) . '...');

            // Parse games
            $games = $this->parseGamesJson($content);
            $this->info('✓ Parsed ' . count($games) . ' games from response');

            if (empty($games)) {
                $this->warn('No games found in response. Content:');
                $this->line(substr($content, 0, 1000));
                return Command::SUCCESS;
            }

            // Store games
            $stats = $this->storeGames($games);

            $this->info("✓ Games stored: {$stats['created']} created, {$stats['updated']} updated, {$stats['skipped']} skipped");

            // Clear cache
            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i)->format('Y-m-d');
                \Illuminate\Support\Facades\Cache::forget("games:date:{$date}");
                \Illuminate\Support\Facades\Cache::forget("nba_schedule_llm:{$date}");
            }
            $this->info('✓ Cache cleared');

            $this->newLine();
            $this->info('Sync complete!');

            // Show summary
            $totalGames = Game::count();
            $this->info("Total games in database: {$totalGames}");

        } catch (\Exception $e) {
            $this->error("Exception: {$e->getMessage()}");
            Log::error('SyncNBASchedule command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Extract text content from OpenAI Responses API format.
     */
    protected function extractContent(array $data): string
    {
        if (isset($data['output'])) {
            foreach ($data['output'] as $item) {
                if ($item['type'] === 'message' && isset($item['content'])) {
                    foreach ($item['content'] as $contentItem) {
                        if ($contentItem['type'] === 'output_text') {
                            return $contentItem['text'] ?? '';
                        }
                    }
                }
            }
        }
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Parse the JSON games array from the response.
     */
    protected function parseGamesJson(string $content): array
    {
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $content = $matches[0];
        }

        $gamesData = json_decode($content, true);

        if (!is_array($gamesData)) {
            $this->warn('Failed to parse JSON from content');
            return [];
        }

        return $gamesData;
    }

    /**
     * Store games with deduplication.
     */
    protected function storeGames(array $gamesData): array
    {
        $teams = \App\Models\Team::all()->keyBy(fn($t) => strtoupper($t->abbreviation));
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($gamesData as $game) {
            $homeAbbr = strtoupper($game['home_team'] ?? '');
            $awayAbbr = strtoupper($game['away_team'] ?? '');
            $gameDate = $game['date'] ?? $game['game_date'] ?? null;

            if (empty($gameDate)) {
                $this->warn("Game missing date: " . json_encode($game));
                $stats['skipped']++;
                continue;
            }

            $homeTeam = $teams->get($homeAbbr);
            $awayTeam = $teams->get($awayAbbr);

            if (!$homeTeam || !$awayTeam) {
                $this->warn("Unknown team: home={$homeAbbr}, away={$awayAbbr}");
                $stats['skipped']++;
                continue;
            }

            $scheduledAt = $this->parseScheduledTime(
                $gameDate,
                $game['scheduled_time'] ?? $game['time'] ?? '7:00 PM',
                $game['timezone'] ?? null
            );

            $externalId = "{$gameDate}-{$homeAbbr}-{$awayAbbr}";

            $existingGame = Game::where('external_id', $externalId)->first();

            if ($existingGame) {
                $existingGame->update([
                    'scheduled_at' => $scheduledAt,
                    'status' => $game['status'] ?? 'scheduled',
                ]);
                $stats['updated']++;
            } else {
                Game::create([
                    'external_id' => $externalId,
                    'home_team_id' => $homeTeam->id,
                    'away_team_id' => $awayTeam->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => $game['status'] ?? 'scheduled',
                ]);
                $stats['created']++;
            }
        }

        return $stats;
    }

    /**
     * Parse a time string with timezone into a Carbon datetime.
     */
    protected function parseScheduledTime(string $date, string $timeStr, ?string $timezone = null): \Illuminate\Support\Carbon
    {
        $tzMap = [
            'PT' => 'America/Los_Angeles',
            'PST' => 'America/Los_Angeles',
            'PDT' => 'America/Los_Angeles',
            'MT' => 'America/Denver',
            'MST' => 'America/Denver',
            'MDT' => 'America/Denver',
            'CT' => 'America/Chicago',
            'CST' => 'America/Chicago',
            'CDT' => 'America/Chicago',
            'ET' => 'America/New_York',
            'EST' => 'America/New_York',
            'EDT' => 'America/New_York',
        ];

        if (preg_match('/\s*(ET|EST|EDT|PT|PST|PDT|CT|CST|CDT|MT|MST|MDT)\s*$/i', $timeStr, $matches)) {
            $timezone = strtoupper($matches[1]);
            $timeStr = preg_replace('/\s*(ET|EST|EDT|PT|PST|PDT|CT|CST|CDT|MT|MST|MDT)\s*$/i', '', $timeStr);
        }

        $timeStr = trim($timeStr);
        $phpTimezone = $tzMap[$timezone ?? 'ET'] ?? 'America/New_York';

        try {
            $datetime = \Illuminate\Support\Carbon::parse("{$date} {$timeStr}", $phpTimezone);
            return $datetime->utc();
        } catch (\Exception $e) {
            return \Illuminate\Support\Carbon::parse("{$date} 19:00:00", 'America/New_York')->utc();
        }
    }
}
