<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AchievementResource;
use App\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AchievementController extends Controller
{
    /**
     * The full catalog, each marked unlocked or not for the current user.
     */
    public function index(Request $request)
    {
        $achievements = Achievement::orderBy('sort_order')->get();
        $unlocked = $request->user()->achievements()->pluck('user_achievements.unlocked_at', 'achievements.id');

        return response()->json([
            'achievements' => $achievements->map(fn (Achievement $a) => [
                'key' => $a->key,
                'icon' => $a->icon,
                'unlocked_at' => $unlocked[$a->id] ?? null,
            ])->values(),
        ]);
    }

    /**
     * Achievements unlocked since the last time the client checked — used
     * to drive the in-game "achievement unlocked" splash. Marks them
     * acknowledged as a side effect, so each one is only ever surfaced once.
     */
    public function unseen(Request $request)
    {
        $user = $request->user();
        $rows = $user->achievements()->wherePivotNull('acknowledged_at')->get();

        if ($rows->isNotEmpty()) {
            DB::table('user_achievements')
                ->where('user_id', $user->id)
                ->whereIn('achievement_id', $rows->pluck('id'))
                ->update(['acknowledged_at' => now()]);
        }

        return AchievementResource::collection($rows);
    }
}
