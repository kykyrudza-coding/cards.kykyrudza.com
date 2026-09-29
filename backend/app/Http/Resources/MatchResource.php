<?php

namespace App\Http\Resources;

use App\Game\Blackjack\BlackjackEngine;
use App\Game\Blackjack\BlackjackState;
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
        $state = BlackjackState::fromArray($this->state);
        $game = (new BlackjackEngine)->publicState($state, $this->viewerId);

        $usernames = $this->matchPlayers->pluck('user.username', 'user_id');

        $game['players'] = array_map(function (array $player) use ($usernames) {
            $player['username'] = $usernames[$player['id']] ?? null;

            return $player;
        }, $game['players']);

        return [
            'id' => $this->id,
            'game_type' => $this->game_type,
            'status' => $this->status,
            'round' => $this->round_number,
            'version' => $this->version,
            'host_id' => $this->lobby->host_id,
            'lobby_code' => $this->lobby->code,
            'default_bet' => $this->lobby->default_bet,
            'manual_bets' => true,
            'confirmed_bets' => (object) $state->confirmedBets,
            'bet_min' => 100,
            'can_start_next_round' => $this->status === 'active' && $state->phase === 'round_finished' && collect($state->players)->contains(fn ($player) => $player->status === 'active' && $player->chips >= 100),
            'game' => $game,
        ];
    }
}
