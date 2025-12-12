<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrate existing ratings from { rating, notes } to { current, future, notes } structure.
     * The existing "rating" value becomes "current", and "future" is set to null.
     */
    public function up(): void
    {
        // Get all reports with ratings
        $reports = DB::table('reports')->whereNotNull('ratings')->get();

        foreach ($reports as $report) {
            $ratings = json_decode($report->ratings, true);

            if (!$ratings) {
                continue;
            }

            $newRatings = [];

            foreach ($ratings as $section => $subsections) {
                $newRatings[$section] = [];

                foreach ($subsections as $subsection => $data) {
                    // Convert old format to new format
                    // Old: { rating: 4, notes: "..." }
                    // New: { current: 4, future: null, notes: "..." }
                    $newRatings[$section][$subsection] = [
                        'current' => $data['rating'] ?? null,
                        'future' => null,
                        'notes' => $data['notes'] ?? null,
                    ];
                }
            }

            DB::table('reports')
                ->where('id', $report->id)
                ->update(['ratings' => json_encode($newRatings)]);
        }
    }

    /**
     * Reverse the migration - convert back to single rating format.
     */
    public function down(): void
    {
        $reports = DB::table('reports')->whereNotNull('ratings')->get();

        foreach ($reports as $report) {
            $ratings = json_decode($report->ratings, true);

            if (!$ratings) {
                continue;
            }

            $oldRatings = [];

            foreach ($ratings as $section => $subsections) {
                $oldRatings[$section] = [];

                foreach ($subsections as $subsection => $data) {
                    // Convert new format back to old format
                    // New: { current: 4, future: 5, notes: "..." }
                    // Old: { rating: 4, notes: "..." }
                    $oldRatings[$section][$subsection] = [
                        'rating' => $data['current'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ];
                }
            }

            DB::table('reports')
                ->where('id', $report->id)
                ->update(['ratings' => json_encode($oldRatings)]);
        }
    }
};
