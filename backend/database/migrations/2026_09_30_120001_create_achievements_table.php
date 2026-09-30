<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('icon');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Display text lives in the frontend locale files under
        // achievements.catalog.<key>.{name,description} — this table is just
        // the stable, orderable catalog of which achievements exist. Renaming
        // one later is a locale-file edit, never a migration.
        $now = now();
        DB::table('achievements')->insert([
            ['key' => 'first_win', 'icon' => 'trophy', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'natural_blackjack', 'icon' => 'star', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'high_roller', 'icon' => 'diamond', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'win_streak_3', 'icon' => 'flame', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'split_master', 'icon' => 'swords', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'dealer_buster', 'icon' => 'target', 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'durak_veteran', 'icon' => 'shield', 'sort_order' => 7, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'marathoner', 'icon' => 'route', 'sort_order' => 8, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'chip_fortune', 'icon' => 'coins', 'sort_order' => 9, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'two_games', 'icon' => 'sparkles', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
