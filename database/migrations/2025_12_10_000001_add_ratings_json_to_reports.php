<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to add JSON-based ratings column to reports table.
 *
 * This replaces the individual rating columns with a flexible JSON structure
 * that supports sections, subsections, ratings (1-5), and per-subsection notes.
 *
 * JSON Structure:
 * {
 *   "offense": {
 *     "shooting": { "rating": 4, "notes": "Great from mid-range" },
 *     "driving": { "rating": 3, "notes": null },
 *     "dribbling": { "rating": null, "notes": "Needs work on left hand" },
 *     ...
 *   },
 *   "defense": { ... },
 *   "intangibles": { ... },
 *   "athleticism": { ... }
 * }
 *
 * This structure allows:
 * - Adding new sections/subsections without schema changes
 * - Per-subsection notes in addition to overall notes
 * - Partial reports (null ratings are allowed)
 * - Future extensibility over 15+ years
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Add JSON column for flexible ratings structure
            $table->json('ratings')->nullable()->after('team_id_at_time');

            // Add game_id to associate reports with specific games
            $table->foreignId('game_id')->nullable()->after('team_id_at_time')->constrained()->onDelete('set null');

            // Index for faster lookups
            $table->index('game_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['game_id']);
            $table->dropForeign(['game_id']);
            $table->dropColumn(['ratings', 'game_id']);
        });
    }
};
