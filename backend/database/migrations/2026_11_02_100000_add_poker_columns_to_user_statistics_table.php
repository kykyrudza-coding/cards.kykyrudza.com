<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_statistics', function (Blueprint $table) {
            $table->unsignedInteger('poker_hands_played')->default(0);
            $table->unsignedInteger('poker_hands_won')->default(0);
            $table->unsignedBigInteger('poker_biggest_pot')->default(0);
            $table->unsignedInteger('poker_matches_won')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('user_statistics', function (Blueprint $table) {
            $table->dropColumn(['poker_hands_played', 'poker_hands_won', 'poker_biggest_pot', 'poker_matches_won']);
        });
    }
};
