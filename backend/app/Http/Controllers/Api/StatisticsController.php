<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserStatisticResource;
use App\Models\UserStatistic;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function show(Request $request)
    {
        $stat = UserStatistic::firstOrNew(['user_id' => $request->user()->id]);

        return new UserStatisticResource($stat);
    }
}
