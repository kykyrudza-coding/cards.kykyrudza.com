<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateLobbyRequest;
use App\Http\Resources\LobbyResource;
use App\Http\Resources\MatchResource;
use App\Models\Lobby;
use App\Services\Lobby\LobbyService;
use Illuminate\Http\Request;

class LobbyController extends Controller
{
    public function __construct(private readonly LobbyService $lobbyService) {}

    public function store(CreateLobbyRequest $request)
    {
        $lobby = $this->lobbyService->create($request->user(), $request->validated());

        return (new LobbyResource($lobby))->response()->setStatusCode(201);
    }

    /**
     * Public lobbies that are still waiting for players, newest first.
     */
    public function index()
    {
        $lobbies = Lobby::query()
            ->where('status', 'waiting')
            ->where('is_private', false)
            ->with('host')
            ->withCount('players')
            ->latest()
            ->limit(30)
            ->get()
            ->filter(fn (Lobby $lobby): bool => $lobby->players_count < $lobby->max_players)
            ->take(12)
            ->values();

        return response()->json([
            'data' => $lobbies->map(fn (Lobby $lobby): array => [
                'code' => $lobby->code,
                'game_type' => $lobby->game_type,
                'max_players' => $lobby->max_players,
                'players_count' => $lobby->players_count,
                'host' => [
                    'username' => $lobby->host->username,
                    'avatar' => $lobby->host->avatar,
                ],
            ]),
        ]);
    }

    public function show(Lobby $lobby)
    {
        return new LobbyResource($this->lobbyService->fresh($lobby));
    }

    public function join(Request $request, Lobby $lobby)
    {
        $data = $request->validate([
            'password' => ['nullable', 'string'],
        ]);

        $lobby = $this->lobbyService->join($request->user(), $lobby, $data['password'] ?? null);

        return new LobbyResource($lobby);
    }

    public function leave(Request $request, Lobby $lobby)
    {
        $this->lobbyService->leave($request->user(), $lobby);

        return response()->json(['message' => 'Left lobby.']);
    }

    public function ready(Request $request, Lobby $lobby)
    {
        $data = $request->validate([
            'ready' => ['required', 'boolean'],
        ]);

        $lobby = $this->lobbyService->setReady($request->user(), $lobby, $data['ready']);

        return new LobbyResource($lobby);
    }

    public function start(Request $request, Lobby $lobby)
    {
        $match = $this->lobbyService->start($request->user(), $lobby);

        return (new MatchResource($match, $request->user()->id))->response()->setStatusCode(201);
    }
}
