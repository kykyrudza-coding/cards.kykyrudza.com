<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LobbyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'game_type' => $this->game_type,
            'status' => $this->status,
            'max_players' => $this->max_players,
            'starting_chips' => $this->starting_chips,
            'default_bet' => $this->default_bet,
            'is_private' => $this->is_private,
            'match_id' => $this->activeMatch?->id,
            'host' => [
                'id' => $this->host->id,
                'username' => $this->host->username,
            ],
            'players' => LobbyPlayerResource::collection($this->whenLoaded('players')),
        ];
    }
}
