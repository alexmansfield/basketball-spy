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
        Schema::table('games', function (Blueprint $table) {
            // Change status from enum to string for more flexibility
            $table->string('status', 50)->default('scheduled')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Note: Reverting to enum may cause data loss if there are non-enum values
            $table->enum('status', ['scheduled', 'live', 'final'])->default('scheduled')->change();
        });
    }
};
