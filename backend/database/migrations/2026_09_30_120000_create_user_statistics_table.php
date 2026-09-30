<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('blackjack_hands_played')->default(0);
            $table->unsignedInteger('blackjack_hands_won')->default(0);
            $table->unsignedInteger('blackjacks_hit')->default(0);
            $table->unsignedInteger('high_roller_wins')->default(0);
            $table->unsignedInteger('dealer_busts_witnessed')->default(0);
            $table->unsignedInteger('splits_performed')->default(0);
            $table->unsignedInteger('durak_matches_played')->default(0);
            $table->unsignedInteger('durak_matches_survived')->default(0);
            $table->unsignedInteger('durak_losses')->default(0);
            $table->unsignedInteger('current_win_streak')->default(0);
            $table->unsignedInteger('best_win_streak')->default(0);
            $table->unsignedInteger('peak_match_chips')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_statistics');
    }
};
