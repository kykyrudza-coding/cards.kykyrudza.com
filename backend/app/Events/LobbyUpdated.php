<?php

namespace App\Events;

use App\Http\Resources\LobbyResource;
use App\Models\Lobby;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LobbyUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Lobby $lobby,
        public string $reason,
    ) {
        $this->lobby->load(['host', 'players.user', 'players.lobby', 'activeMatch']);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("lobby.{$this->lobby->code}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'LobbyUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'reason' => $this->reason,
            'lobby' => (new LobbyResource($this->lobby))->resolve(),
        ];
    }
}
