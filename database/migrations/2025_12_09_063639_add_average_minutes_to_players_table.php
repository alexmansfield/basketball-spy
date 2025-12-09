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
            $table->decimal('average_minutes_played', 5, 2)->nullable()->after('minutes_played');
            $table->index('average_minutes_played');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex(['average_minutes_played']);
            $table->dropColumn('average_minutes_played');
        });
    }
};
