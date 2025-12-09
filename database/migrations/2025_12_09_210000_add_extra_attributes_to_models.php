<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add extra_attributes JSON column to teams
        Schema::table('teams', function (Blueprint $table) {
            $table->json('extra_attributes')->nullable()->after('arena_longitude');
            $table->unsignedBigInteger('balldontlie_id')->nullable()->after('extra_attributes');
            $table->index('balldontlie_id');
        });

        // Add extra_attributes JSON column to players
        Schema::table('players', function (Blueprint $table) {
            $table->json('extra_attributes')->nullable()->after('stats_synced_at');
            $table->unsignedBigInteger('balldontlie_id')->nullable()->after('extra_attributes');
            $table->index('balldontlie_id');
        });

        // Add extra fields to games for BallDontLie data
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedBigInteger('balldontlie_id')->nullable()->after('external_id');
            $table->integer('home_team_score')->nullable()->after('balldontlie_id');
            $table->integer('away_team_score')->nullable()->after('home_team_score');
            $table->integer('period')->nullable()->after('away_team_score');
            $table->string('time', 20)->nullable()->after('period');
            $table->boolean('postseason')->default(false)->after('time');
            $table->integer('season')->nullable()->after('postseason');
            $table->json('extra_attributes')->nullable()->after('season');
            $table->index('balldontlie_id');
            $table->index('season');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['balldontlie_id']);
            $table->dropColumn(['extra_attributes', 'balldontlie_id']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex(['balldontlie_id']);
            $table->dropColumn(['extra_attributes', 'balldontlie_id']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['balldontlie_id']);
            $table->dropIndex(['season']);
            $table->dropColumn([
                'balldontlie_id',
                'home_team_score',
                'away_team_score',
                'period',
                'time',
                'postseason',
                'season',
                'extra_attributes',
            ]);
        });
    }
};
