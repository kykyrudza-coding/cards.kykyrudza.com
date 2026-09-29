<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LobbyPlayerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'username' => $this->user->username,
            'seat' => $this->seat,
            'is_ready' => (bool) $this->is_ready,
            'is_host' => $this->user_id === $this->lobby->host_id,
        ];
    }
}
