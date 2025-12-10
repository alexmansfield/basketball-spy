<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Display a listing of reports.
     *
     * GET /api/reports?player_id=1&user_id=1
     *
     * Returns reports scoped to user's organization.
     * Supports filtering by player and date range.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Report::with(['player.team', 'user:id,name', 'game'])
            ->where('user_id', $user->id);

        // If user is org_admin or super_admin, show all reports in their organization
        if ($user->isOrgAdmin() || $user->isSuperAdmin()) {
            $query = Report::with(['player.team', 'user:id,name', 'game']);

            if (!$user->isSuperAdmin()) {
                // Org admins see only their organization's reports
                $query->whereHas('user', function ($q) use ($user) {
                    $q->where('organization_id', $user->organization_id);
                });
            }
        }

        // Filter by player
        if ($request->has('player_id')) {
            $query->where('player_id', $request->player_id);
        }

        // Filter by game
        if ($request->has('game_id')) {
            $query->where('game_id', $request->game_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Order by most recent first
        $query->latest('created_at');

        $perPage = $request->get('per_page', 20);
        $reports = $query->paginate($perPage);

        return response()->json($reports);
    }

    /**
     * Get rating structure definition.
     *
     * GET /api/reports/structure
     *
     * Returns the available sections and subsections for ratings.
     */
    public function structure(): JsonResponse
    {
        return response()->json([
            'structure' => Report::RATING_STRUCTURE,
        ]);
    }

    /**
     * Get or create a report for a player in a specific game.
     *
     * GET /api/reports/current?player_id=1&game_id=1
     *
     * This endpoint is used by the mobile app to load existing ratings
     * when a scout selects a player. Creates a new report if none exists.
     */
    public function current(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'required|exists:players,id',
            'game_id' => 'nullable|exists:games,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $playerId = $request->player_id;
        $gameId = $request->game_id;

        // Look for existing report by this user for this player in this game
        $query = Report::where('user_id', $user->id)
            ->where('player_id', $playerId);

        if ($gameId) {
            $query->where('game_id', $gameId);
        } else {
            $query->whereNull('game_id');
        }

        $report = $query->first();

        // If no report exists, create one
        if (!$report) {
            $player = Player::findOrFail($playerId);

            $report = new Report([
                'user_id' => $user->id,
                'player_id' => $playerId,
                'team_id_at_time' => $player->team_id,
                'game_id' => $gameId,
                'notes' => null,
            ]);

            // Initialize empty ratings structure
            $report->initializeRatings();
            $report->save();
        }

        $report->load(['player.team', 'user:id,name', 'game']);

        // Add computed attributes
        $report->append(['average_rating', 'ratings_count', 'total_ratings', 'is_complete', 'completion_percentage']);

        return response()->json($report);
    }

    /**
     * Store a newly created report.
     *
     * POST /api/reports
     *
     * Creates a new report with the JSON ratings structure.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'required|exists:players,id',
            'game_id' => 'nullable|exists:games,id',
            'ratings' => 'nullable|array',
            'ratings.*' => 'array',
            'ratings.*.*' => 'array',
            'ratings.*.*.rating' => 'nullable|integer|min:1|max:5',
            'ratings.*.*.notes' => 'nullable|string|max:1000',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $player = Player::findOrFail($request->player_id);

        $report = new Report([
            'user_id' => $user->id,
            'player_id' => $request->player_id,
            'team_id_at_time' => $player->team_id,
            'game_id' => $request->game_id,
            'ratings' => $request->ratings,
            'notes' => $request->notes,
            'synced_at' => Carbon::now(),
        ]);

        // Initialize ratings if not provided
        if (!$report->ratings) {
            $report->initializeRatings();
        }

        $report->save();
        $report->load(['player.team', 'user:id,name', 'game']);
        $report->append(['average_rating', 'ratings_count', 'total_ratings', 'is_complete', 'completion_percentage']);

        return response()->json($report, 201);
    }

    /**
     * Display the specified report.
     *
     * GET /api/reports/{id}
     *
     * Returns single report with player/team relationships.
     * Organization-scoped authorization.
     */
    public function show(Report $report): JsonResponse
    {
        $user = request()->user();

        // Authorization: scouts can only see their own reports
        if ($user->isScout() && $report->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Org admins can only see reports from their organization
        if ($user->isOrgAdmin() && $report->user->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $report->load(['player.team', 'user:id,name', 'game']);
        $report->append(['average_rating', 'ratings_count', 'total_ratings', 'is_complete', 'completion_percentage']);

        return response()->json($report);
    }

    /**
     * Update the specified report (full update).
     *
     * PUT /api/reports/{id}
     */
    public function update(Request $request, Report $report): JsonResponse
    {
        $user = $request->user();

        // Only the report creator can update their own report
        if ($report->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'ratings' => 'nullable|array',
            'ratings.*' => 'array',
            'ratings.*.*' => 'array',
            'ratings.*.*.rating' => 'nullable|integer|min:1|max:5',
            'ratings.*.*.notes' => 'nullable|string|max:1000',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update ratings if provided
        if ($request->has('ratings')) {
            $report->ratings = $request->ratings;
        }

        // Update notes if provided
        if ($request->has('notes')) {
            $report->notes = $request->notes;
        }

        $report->synced_at = Carbon::now();
        $report->save();

        $report->load(['player.team', 'user:id,name', 'game']);
        $report->append(['average_rating', 'ratings_count', 'total_ratings', 'is_complete', 'completion_percentage']);

        return response()->json($report);
    }

    /**
     * Partial update for auto-save functionality.
     *
     * PATCH /api/reports/{id}
     *
     * Accepts partial updates for individual rating changes.
     * This is the endpoint used by the mobile app for auto-save.
     */
    public function patch(Request $request, Report $report): JsonResponse
    {
        $user = $request->user();

        // Only the report creator can update their own report
        if ($report->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            // For updating a single rating
            'section' => 'nullable|string|in:offense,defense,intangibles,athleticism',
            'subsection' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'subsection_notes' => 'nullable|string|max:1000',

            // For updating overall notes
            'notes' => 'nullable|string',

            // For full ratings update
            'ratings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle single rating update
        if ($request->has('section') && $request->has('subsection')) {
            $section = $request->section;
            $subsection = $request->subsection;

            // Validate subsection exists in structure
            if (!isset(Report::RATING_STRUCTURE[$section][$subsection])) {
                return response()->json(['error' => 'Invalid subsection'], 422);
            }

            // Update rating if provided
            if ($request->has('rating')) {
                $report->setRating($section, $subsection, $request->rating);
            }

            // Update subsection notes if provided
            if ($request->has('subsection_notes')) {
                $report->setSubsectionNotes($section, $subsection, $request->subsection_notes);
            }
        }

        // Handle full ratings update
        if ($request->has('ratings')) {
            $report->ratings = $request->ratings;
        }

        // Handle overall notes update
        if ($request->has('notes')) {
            $report->notes = $request->notes;
        }

        $report->synced_at = Carbon::now();
        $report->save();

        $report->load(['player.team', 'user:id,name', 'game']);
        $report->append(['average_rating', 'ratings_count', 'total_ratings', 'is_complete', 'completion_percentage']);

        return response()->json($report);
    }

    /**
     * Remove the specified report.
     *
     * DELETE /api/reports/{id}
     */
    public function destroy(Report $report): JsonResponse
    {
        $user = request()->user();

        // Only the report creator or super admin can delete
        if ($report->user_id !== $user->id && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $report->delete();

        return response()->json(['message' => 'Report deleted successfully']);
    }

    /**
     * Batch sync endpoint for local-first architecture.
     *
     * POST /api/reports/sync
     *
     * Accepts array of reports from mobile app.
     * Returns conflicts for resolution.
     */
    public function sync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reports' => 'required|array',
            'reports.*.id' => 'nullable|exists:reports,id',
            'reports.*.player_id' => 'required|exists:players,id',
            'reports.*.game_id' => 'nullable|exists:games,id',
            'reports.*.ratings' => 'nullable|array',
            'reports.*.notes' => 'nullable|string',
            'reports.*.local_updated_at' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $synced = [];
        $conflicts = [];

        foreach ($request->reports as $reportData) {
            // If report has an ID, check for conflicts
            if (isset($reportData['id'])) {
                $existingReport = Report::find($reportData['id']);

                if ($existingReport) {
                    // Check for conflict: server updated after local
                    $localUpdated = Carbon::parse($reportData['local_updated_at']);
                    if ($existingReport->updated_at > $localUpdated) {
                        $conflicts[] = [
                            'id' => $existingReport->id,
                            'server_version' => $existingReport,
                            'client_version' => $reportData,
                        ];
                        continue;
                    }

                    // No conflict, update
                    $existingReport->ratings = $reportData['ratings'] ?? $existingReport->ratings;
                    $existingReport->notes = $reportData['notes'] ?? $existingReport->notes;
                    $existingReport->synced_at = Carbon::now();
                    $existingReport->save();
                    $synced[] = $existingReport;
                } else {
                    // Report deleted on server
                    $conflicts[] = [
                        'id' => $reportData['id'],
                        'error' => 'Report not found on server (possibly deleted)',
                        'client_version' => $reportData,
                    ];
                }
            } else {
                // New report from mobile, create it
                $player = Player::findOrFail($reportData['player_id']);

                $newReport = new Report([
                    'user_id' => $user->id,
                    'player_id' => $reportData['player_id'],
                    'team_id_at_time' => $player->team_id,
                    'game_id' => $reportData['game_id'] ?? null,
                    'ratings' => $reportData['ratings'] ?? null,
                    'notes' => $reportData['notes'] ?? null,
                    'synced_at' => Carbon::now(),
                ]);

                if (!$newReport->ratings) {
                    $newReport->initializeRatings();
                }

                $newReport->save();
                $synced[] = $newReport;
            }
        }

        return response()->json([
            'synced' => $synced,
            'conflicts' => $conflicts,
            'synced_count' => count($synced),
            'conflict_count' => count($conflicts),
        ]);
    }
}
