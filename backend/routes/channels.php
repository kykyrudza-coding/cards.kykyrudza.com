<?php

use App\Models\GameMatch;
use App\Models\Lobby;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('lobby.{code}', function (User $user, string $code) {
    $lobby = Lobby::where('code', $code)->first();

    if (! $lobby) {
        return false;
    }

    if (! $lobby->players()->where('user_id', $user->id)->exists()) {
        return false;
    }

    return ['id' => $user->id, 'username' => $user->username];
});

Broadcast::channel('match.{matchId}', function (User $user, int $matchId) {
    $match = GameMatch::find($matchId);

    if (! $match) {
        return false;
    }

    if (! $match->matchPlayers()->where('user_id', $user->id)->exists()) {
        return false;
    }

    return ['id' => $user->id, 'username' => $user->username];
});
