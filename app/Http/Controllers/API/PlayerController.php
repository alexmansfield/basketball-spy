<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class PlayerController extends Controller
{
    /**
     * Cache TTL in seconds (15 minutes for players - roster changes occasionally)
     */
    private const CACHE_TTL = 900;
    /**
     * Display a listing of players.
     *
     * GET /api/players?team_id=1&sort=minutes
     *
     * Returns players for a specific team, sorted by jersey number by default.
     * Use sort=minutes to sort by combined minutes rank (requires SportsBlaze API data).
     *
     * If no team_id provided, returns paginated list of all players.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate query parameters
        $validator = Validator::make($request->all(), [
            'team_id' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:100',
            'sort' => 'nullable|string|in:jersey,minutes',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $teamId = $request->input('team_id');
        $search = $request->input('search');
        $sort = $request->input('sort', 'jersey');

        // Build cache key - only cache team-specific requests (most common case)
        if ($teamId) {
            $cacheKey = "players:team:{$teamId}:{$sort}:" . md5($search ?? '');

            $players = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($teamId, $search, $sort) {
                if ($sort === 'minutes') {
                    return $this->getPlayersRankedByMinutes($teamId, $search)->toArray();
                }
                return $this->getPlayersSortedByJersey($teamId, $search)->toArray();
            });

            return response()->json($players);
        }

        // Non-team-specific requests - paginated, not cached (less common)
        if ($sort === 'minutes') {
            $players = $this->getPlayersRankedByMinutes($teamId, $search);
        } else {
            $players = $this->getPlayersSortedByJersey($teamId, $search);
        }

        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $perPage;

        $total = $players->count();
        $paginatedPlayers = $players->slice($offset, $perPage)->values();

        return response()->json([
            'data' => $paginatedPlayers,
            'current_page' => (int) $page,
            'per_page' => (int) $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Get players sorted by jersey number.
     */
    protected function getPlayersSortedByJersey(?string $teamId, ?string $search)
    {
        $query = Player::with('team')->where('is_active', true);

        if ($teamId) {
            // Support lookup by numeric ID or team abbreviation
            if (is_numeric($teamId)) {
                $query->where('team_id', $teamId);
            } else {
                $query->whereHas('team', function ($q) use ($teamId) {
                    $q->whereRaw('LOWER(abbreviation) = ?', [strtolower($teamId)]);
                });
            }
        }

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->get()->sortBy(fn($p) => (int) $p->jersey)->values();
    }

    /**
     * Get players ranked by combined minutes metrics.
     *
     * Ranks players by both total_minutes and average_minutes, then sorts
     * by the average of these ranks. Players with no stats go to the end.
     */
    protected function getPlayersRankedByMinutes(?string $teamId, ?string $search)
    {
        $query = Player::with('team')->where('is_active', true);

        if ($teamId) {
            // Support lookup by numeric ID or team abbreviation
            if (is_numeric($teamId)) {
                $query->where('team_id', $teamId);
            } else {
                $query->whereHas('team', function ($q) use ($teamId) {
                    $q->whereRaw('LOWER(abbreviation) = ?', [strtolower($teamId)]);
                });
            }
        }

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $players = $query->get();

        // Separate players with stats from those without
        $withStats = $players->filter(fn($p) => $p->minutes_played !== null && $p->average_minutes_played !== null);
        $withoutStats = $players->filter(fn($p) => $p->minutes_played === null || $p->average_minutes_played === null);

        if ($withStats->isEmpty()) {
            // No stats available, fall back to jersey number sorting
            return $players->sortBy(fn($p) => (int) $p->jersey)->values();
        }

        // Rank by total minutes (higher minutes = lower rank number = better)
        $sortedByTotal = $withStats->sortByDesc('minutes_played')->values();
        $totalRanks = $sortedByTotal->mapWithKeys(fn($p, $idx) => [$p->id => $idx + 1]);

        // Rank by average minutes (higher average = lower rank number = better)
        $sortedByAvg = $withStats->sortByDesc('average_minutes_played')->values();
        $avgRanks = $sortedByAvg->mapWithKeys(fn($p, $idx) => [$p->id => $idx + 1]);

        // Calculate combined rank (average of both ranks)
        $withStats = $withStats->map(function ($player) use ($totalRanks, $avgRanks) {
            $player->total_rank = $totalRanks[$player->id];
            $player->avg_rank = $avgRanks[$player->id];
            $player->combined_rank = ($player->total_rank + $player->avg_rank) / 2;
            return $player;
        });

        // Sort by combined rank (lowest first), then jersey as tiebreaker
        $sorted = $withStats->sortBy([
            ['combined_rank', 'asc'],
            [fn($p) => (int) $p->jersey, 'asc'],
        ])->values();

        // Append players without stats, sorted by jersey
        $withoutStatsSorted = $withoutStats->sortBy(fn($p) => (int) $p->jersey)->values();

        return $sorted->concat($withoutStatsSorted)->values();
    }

    /**
     * Display the specified player.
     *
     * GET /api/players/{id}
     *
     * Returns player with team and latest reports.
     */
    public function show(Player $player): JsonResponse
    {
        // Load team relationship
        $player->load('team');

        // Load latest 10 reports for this player
        $player->load(['reports' => function ($query) {
            $query->latest('created_at')->limit(10)->with('user:id,name');
        }]);

        return response()->json($player);
    }

    /**
     * Store a newly created player.
     *
     * POST /api/admin/players
     * Requires super_admin role.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'name' => 'required|string|max:255',
            'jersey' => 'nullable|string|max:10',
            'position' => 'nullable|string|max:50',
            'height' => 'nullable|string|max:20',
            'weight' => 'nullable|string|max:20',
            'headshot_url' => 'nullable|url|max:500',
            'is_active' => 'boolean',
        ]);

        $player = Player::create($validated);
        $player->load('team');

        Cache::flush(); // Clear player caches

        return response()->json($player, 201);
    }

    /**
     * Update the specified player.
     *
     * PUT /api/admin/players/{player}
     * Requires super_admin role.
     */
    public function update(Request $request, Player $player): JsonResponse
    {
        $validated = $request->validate([
            'team_id' => 'sometimes|exists:teams,id',
            'name' => 'sometimes|string|max:255',
            'jersey' => 'nullable|string|max:10',
            'position' => 'nullable|string|max:50',
            'height' => 'nullable|string|max:20',
            'weight' => 'nullable|string|max:20',
            'headshot_url' => 'nullable|url|max:500',
            'is_active' => 'boolean',
        ]);

        $player->update($validated);
        $player->load('team');

        Cache::flush(); // Clear player caches

        return response()->json($player);
    }

    /**
     * Remove the specified player (soft delete).
     *
     * DELETE /api/admin/players/{player}
     * Requires super_admin role.
     */
    public function destroy(Player $player): JsonResponse
    {
        $player->delete();

        Cache::flush(); // Clear player caches

        return response()->json(['message' => 'Player deleted successfully']);
    }

    /**
     * Merge duplicate players.
     *
     * POST /api/admin/players/merge
     * Requires super_admin role.
     *
     * Merges source player(s) into target player, moving all reports.
     */
    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_id' => 'required|exists:players,id',
            'source_ids' => 'required|array|min:1',
            'source_ids.*' => 'exists:players,id|different:target_id',
        ]);

        $targetPlayer = Player::findOrFail($validated['target_id']);
        $sourcePlayers = Player::whereIn('id', $validated['source_ids'])->get();

        $mergedReportsCount = 0;

        foreach ($sourcePlayers as $sourcePlayer) {
            // Move all reports from source to target
            $reportsCount = $sourcePlayer->reports()->update(['player_id' => $targetPlayer->id]);
            $mergedReportsCount += $reportsCount;

            // Soft delete the source player
            $sourcePlayer->delete();
        }

        Cache::flush(); // Clear player caches

        $targetPlayer->load('team');

        return response()->json([
            'message' => 'Players merged successfully',
            'target' => $targetPlayer,
            'merged_reports_count' => $mergedReportsCount,
            'deleted_players_count' => count($validated['source_ids']),
        ]);
    }
}
