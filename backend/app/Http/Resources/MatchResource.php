<?php

namespace App\Http\Resources;

use App\Game\Blackjack\BlackjackState;
use App\Game\GameCatalog;
use App\Models\GameMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    public function __construct($resource, private readonly int $viewerId)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = GameCatalog::hydrateState($this->game_type, $this->state);
        $engine = GameCatalog::engine($this->game_type);
        $game = $engine->publicState($state, $this->viewerId);

        $usernames = $this->matchPlayers->pluck('user.username', 'user_id');
        $avatars = $this->matchPlayers->pluck('user.avatar', 'user_id');

        $game['players'] = array_map(function (array $player) use ($usernames, $avatars) {
            $player['username'] = $usernames[$player['id']] ?? null;
            $player['avatar'] = $avatars[$player['id']] ?? null;

            return $player;
        }, $game['players']);

        $data = [
            'id' => $this->id,
            'game_type' => $this->game_type,
            'status' => $this->status,
            'round' => $this->round_number,
            'version' => $this->version,
            'host_id' => $this->lobby->host_id,
            'lobby_code' => $this->lobby->code,
            // Set once a rematch has been started from this (finished) match.
            'next_match_id' => GameMatch::query()
                ->where('lobby_id', $this->lobby_id)
                ->where('id', '>', $this->id)
                ->orderBy('id')
                ->value('id'),
            'game' => $game,
        ];

        // Betting only exists for Blackjack — Durak has no chip economy.
        if ($state instanceof BlackjackState) {
            $data += [
                'default_bet' => $this->lobby->default_bet,
                'manual_bets' => true,
                'confirmed_bets' => (object) $state->confirmedBets,
                'bet_min' => 100,
                'can_start_next_round' => $this->status === 'active' && $state->phase === 'round_finished' && collect($state->players)->contains(fn ($player) => $player->status === 'active' && $player->chips >= 100),
            ];
        }

        return $data;
    }
}
