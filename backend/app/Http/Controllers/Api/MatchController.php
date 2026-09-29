<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Models\GameMatch;
use App\Services\Match\MatchService;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function __construct(private readonly MatchService $matchService) {}

    public function show(Request $request, GameMatch $match)
    {
        $this->authorizeParticipant($request, $match);

        return new MatchResource($match->loadMissing(['matchPlayers.user', 'lobby']), $request->user()->id);
    }

    public function hit(Request $request, GameMatch $match)
    {
        return $this->act($request, $match, 'hit');
    }

    public function stand(Request $request, GameMatch $match)
    {
        return $this->act($request, $match, 'stand');
    }

    public function double(Request $request, GameMatch $match)
    {
        return $this->act($request, $match, 'double');
    }

    public function split(Request $request, GameMatch $match)
    {
        return $this->act($request, $match, 'split');
    }

    public function placeBet(Request $request, GameMatch $match)
    {
        $this->authorizeParticipant($request, $match);
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:100'],
            'expected_round' => ['required', 'integer', 'min:1'],
        ]);
        $match = $this->matchService->placeBet($match, $request->user(), $validated['amount'], $validated['expected_round']);

        return new MatchResource($match, $request->user()->id);
    }

    public function nextRound(Request $request, GameMatch $match)
    {
        $validated = $request->validate(['expected_round' => ['sometimes', 'integer', 'min:1']]);
        $match = $this->matchService->nextRound($match, $request->user(), $validated['expected_round'] ?? null);

        return new MatchResource($match, $request->user()->id);
    }

    public function finish(Request $request, GameMatch $match)
    {
        $match = $this->matchService->finish($match, $request->user());

        return new MatchResource($match, $request->user()->id);
    }

    private function act(Request $request, GameMatch $match, string $action)
    {
        $this->authorizeParticipant($request, $match);

        $match = $this->matchService->performAction($match, $request->user(), $action);

        return new MatchResource($match, $request->user()->id);
    }

    private function authorizeParticipant(Request $request, GameMatch $match): void
    {
        abort_unless(
            $match->matchPlayers()->where('user_id', $request->user()->id)->exists(),
            403,
            'You are not a participant in this match.'
        );
    }
}
