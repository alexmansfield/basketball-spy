<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration to convert existing individual rating columns to JSON format
 * and optionally remove the old columns.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, migrate any existing data to the new JSON format
        DB::table('reports')->orderBy('id')->chunk(100, function ($reports) {
            foreach ($reports as $report) {
                $ratings = [
                    'offense' => [
                        'shooting' => ['rating' => $report->offense_shooting, 'notes' => null],
                        'finishing' => ['rating' => $report->offense_finishing, 'notes' => null],
                        'driving' => ['rating' => $report->offense_driving, 'notes' => null],
                        'dribbling' => ['rating' => $report->offense_dribbling, 'notes' => null],
                        'creating' => ['rating' => $report->offense_creating, 'notes' => null],
                        'passing' => ['rating' => $report->offense_passing, 'notes' => null],
                    ],
                    'defense' => [
                        'one_on_one' => ['rating' => $report->defense_one_on_one, 'notes' => null],
                        'blocking' => ['rating' => $report->defense_blocking, 'notes' => null],
                        'team_defense' => ['rating' => $report->defense_team_defense, 'notes' => null],
                        'rebounding' => ['rating' => $report->defense_rebounding, 'notes' => null],
                    ],
                    'intangibles' => [
                        'effort' => ['rating' => $report->intangibles_effort, 'notes' => null],
                        'role_acceptance' => ['rating' => $report->intangibles_role_acceptance, 'notes' => null],
                        'iq' => ['rating' => $report->intangibles_iq, 'notes' => null],
                        'awareness' => ['rating' => $report->intangibles_awareness, 'notes' => null],
                    ],
                    'athleticism' => [
                        'hands' => ['rating' => $report->athleticism_hands, 'notes' => null],
                        'length' => ['rating' => $report->athleticism_length, 'notes' => null],
                        'quickness' => ['rating' => $report->athleticism_quickness, 'notes' => null],
                        'jumping' => ['rating' => $report->athleticism_jumping, 'notes' => null],
                        'strength' => ['rating' => $report->athleticism_strength, 'notes' => null],
                        'coordination' => ['rating' => $report->athleticism_coordination, 'notes' => null],
                    ],
                ];

                // Only update if there's at least one rating
                $hasRating = false;
                foreach ($ratings as $section) {
                    foreach ($section as $subsection) {
                        if ($subsection['rating'] !== null) {
                            $hasRating = true;
                            break 2;
                        }
                    }
                }

                if ($hasRating) {
                    DB::table('reports')
                        ->where('id', $report->id)
                        ->update(['ratings' => json_encode($ratings)]);
                }
            }
        });

        // Now drop the old columns
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn([
                // Offense
                'offense_shooting',
                'offense_finishing',
                'offense_driving',
                'offense_dribbling',
                'offense_creating',
                'offense_passing',
                // Defense
                'defense_one_on_one',
                'defense_blocking',
                'defense_team_defense',
                'defense_rebounding',
                // Intangibles
                'intangibles_effort',
                'intangibles_role_acceptance',
                'intangibles_iq',
                'intangibles_awareness',
                // Athleticism
                'athleticism_hands',
                'athleticism_length',
                'athleticism_quickness',
                'athleticism_jumping',
                'athleticism_strength',
                'athleticism_coordination',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the old columns
        Schema::table('reports', function (Blueprint $table) {
            // OFFENSE ratings
            $table->unsignedTinyInteger('offense_shooting')->nullable();
            $table->unsignedTinyInteger('offense_finishing')->nullable();
            $table->unsignedTinyInteger('offense_driving')->nullable();
            $table->unsignedTinyInteger('offense_dribbling')->nullable();
            $table->unsignedTinyInteger('offense_creating')->nullable();
            $table->unsignedTinyInteger('offense_passing')->nullable();

            // DEFENSE ratings
            $table->unsignedTinyInteger('defense_one_on_one')->nullable();
            $table->unsignedTinyInteger('defense_blocking')->nullable();
            $table->unsignedTinyInteger('defense_team_defense')->nullable();
            $table->unsignedTinyInteger('defense_rebounding')->nullable();

            // INTANGIBLES ratings
            $table->unsignedTinyInteger('intangibles_effort')->nullable();
            $table->unsignedTinyInteger('intangibles_role_acceptance')->nullable();
            $table->unsignedTinyInteger('intangibles_iq')->nullable();
            $table->unsignedTinyInteger('intangibles_awareness')->nullable();

            // ATHLETICISM ratings
            $table->unsignedTinyInteger('athleticism_hands')->nullable();
            $table->unsignedTinyInteger('athleticism_length')->nullable();
            $table->unsignedTinyInteger('athleticism_quickness')->nullable();
            $table->unsignedTinyInteger('athleticism_jumping')->nullable();
            $table->unsignedTinyInteger('athleticism_strength')->nullable();
            $table->unsignedTinyInteger('athleticism_coordination')->nullable();
        });

        // Migrate data back from JSON to columns
        DB::table('reports')->whereNotNull('ratings')->orderBy('id')->chunk(100, function ($reports) {
            foreach ($reports as $report) {
                $ratings = json_decode($report->ratings, true);
                if (!$ratings) continue;

                $updateData = [];

                // Extract ratings from JSON
                if (isset($ratings['offense'])) {
                    foreach ($ratings['offense'] as $key => $value) {
                        $updateData["offense_{$key}"] = $value['rating'] ?? null;
                    }
                }
                if (isset($ratings['defense'])) {
                    foreach ($ratings['defense'] as $key => $value) {
                        $updateData["defense_{$key}"] = $value['rating'] ?? null;
                    }
                }
                if (isset($ratings['intangibles'])) {
                    foreach ($ratings['intangibles'] as $key => $value) {
                        $updateData["intangibles_{$key}"] = $value['rating'] ?? null;
                    }
                }
                if (isset($ratings['athleticism'])) {
                    foreach ($ratings['athleticism'] as $key => $value) {
                        $updateData["athleticism_{$key}"] = $value['rating'] ?? null;
                    }
                }

                if (!empty($updateData)) {
                    DB::table('reports')
                        ->where('id', $report->id)
                        ->update($updateData);
                }
            }
        });
    }
};
