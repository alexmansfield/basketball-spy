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
        Schema::table('players', function (Blueprint $table) {
            $table->integer('minutes_played')->nullable()->after('headshot_url');
            $table->string('sportsblaze_player_id')->nullable()->after('minutes_played');
            $table->timestamp('stats_synced_at')->nullable()->after('sportsblaze_player_id');

            $table->index('minutes_played');
            $table->index('sportsblaze_player_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex(['minutes_played']);
            $table->dropIndex(['sportsblaze_player_id']);
            $table->dropColumn(['minutes_played', 'sportsblaze_player_id', 'stats_synced_at']);
        });
    }
};
