<?php

namespace App\Services\Lobby;

use App\Events\LobbyUpdated;
use App\Models\GameMatch;
use App\Models\Lobby;
use App\Models\LobbyPlayer;
use App\Models\User;
use App\Services\Match\MatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LobbyService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 6;

    public function __construct(private readonly MatchService $matchService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Lobby
    {
        $isPrivate = (bool) ($data['is_private'] ?? false);
        $startingChips = $data['starting_chips'] ?? 5000;
        $defaultBet = $data['default_bet'] ?? 100;

        if ($defaultBet > $startingChips) {
            throw ValidationException::withMessages([
                'default_bet' => ['The default bet cannot exceed the starting chips.'],
            ]);
        }

        $lobby = Lobby::create([
            'code' => $this->generateUniqueCode(),
            'host_id' => $user->id,
            'game_type' => $data['game_type'] ?? 'blackjack',
            'status' => 'waiting',
            'max_players' => $data['max_players'] ?? 4,
            'starting_chips' => $startingChips,
            'default_bet' => $defaultBet,
            'events_enabled' => ($data['game_type'] ?? 'blackjack') === 'blackjack' && (bool) ($data['events_enabled'] ?? false),
            'is_private' => $isPrivate,
            'password' => $isPrivate && ! empty($data['password']) ? Hash::make($data['password']) : null,
        ]);

        LobbyPlayer::create([
            'lobby_id' => $lobby->id,
            'user_id' => $user->id,
            'seat' => 0,
            'is_ready' => false,
            'joined_at' => now(),
        ]);

        return $this->fresh($lobby);
    }

    public function join(User $user, Lobby $lobby, ?string $password): Lobby
    {
        abort_if($lobby->status !== 'waiting', 422, 'Lobby is not accepting new players.');

        abort_if(
            $lobby->players()->where('user_id', $user->id)->exists(),
            422,
            'You are already in this lobby.'
        );

        $playerCount = $lobby->players()->count();
        abort_if($playerCount >= $lobby->max_players, 422, 'Lobby is full.');

        if ($lobby->is_private) {
            if (! $password || ! Hash::check($password, $lobby->password)) {
                throw ValidationException::withMessages([
                    'password' => ['Invalid password.'],
                ]);
            }
        }

        LobbyPlayer::create([
            'lobby_id' => $lobby->id,
            'user_id' => $user->id,
            'seat' => $this->nextAvailableSeat($lobby),
            'is_ready' => false,
            'joined_at' => now(),
        ]);

        $lobby = $this->fresh($lobby);

        LobbyUpdated::dispatch($lobby, 'player_joined');

        return $lobby;
    }

    public function leave(User $user, Lobby $lobby): void
    {
        $membership = $lobby->players()->where('user_id', $user->id)->first();

        abort_if(! $membership, 422, 'You are not in this lobby.');

        $wasHost = $lobby->host_id === $user->id;
        $membership->delete();

        $remaining = $lobby->players()->orderBy('joined_at')->get();

        if ($wasHost) {
            if ($remaining->isNotEmpty()) {
                $lobby->update(['host_id' => $remaining->first()->user_id]);
                $reason = 'host_changed';
            } else {
                $lobby->update(['status' => 'closed']);
                $reason = 'player_left';
            }
        } else {
            $reason = 'player_left';
        }

        if ($remaining->isNotEmpty()) {
            LobbyUpdated::dispatch($this->fresh($lobby), $reason);
        }
    }

    public function setReady(User $user, Lobby $lobby, bool $ready): Lobby
    {
        abort_if($lobby->status !== 'waiting', 422, 'Lobby is not accepting ready changes.');

        $membership = $lobby->players()->where('user_id', $user->id)->first();

        abort_if(! $membership, 422, 'You are not in this lobby.');

        $membership->update(['is_ready' => $ready]);

        $lobby = $this->fresh($lobby);

        LobbyUpdated::dispatch($lobby, 'ready_changed');

        return $lobby;
    }

    public function start(User $user, Lobby $lobby): GameMatch
    {
        abort_if($lobby->host_id !== $user->id, 403, 'Only the host can start the lobby.');
        abort_if($lobby->status !== 'waiting', 422, 'Lobby has already started.');

        $players = $lobby->players()->get();

        abort_if($players->isEmpty(), 422, 'Lobby has no players.');
        abort_if($lobby->game_type === 'poker' && $players->count() < 2, 422, 'Poker needs at least two players.');
        abort_if($players->contains(fn (LobbyPlayer $player) => ! $player->is_ready), 422, 'All players must be ready.');

        return DB::transaction(function () use ($lobby) {
            $match = $this->matchService->createForLobby($lobby);

            $lobby->update(['status' => 'started']);

            LobbyUpdated::dispatch($this->fresh($lobby), 'started');

            return $match;
        });
    }

    public function fresh(Lobby $lobby): Lobby
    {
        return $lobby->fresh(['host', 'players.user', 'players.lobby', 'activeMatch']);
    }

    private function nextAvailableSeat(Lobby $lobby): int
    {
        $taken = $lobby->players()->pluck('seat')->all();

        for ($seat = 0; $seat < $lobby->max_players; $seat++) {
            if (! in_array($seat, $taken, true)) {
                return $seat;
            }
        }

        abort(422, 'Lobby is full.');
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = substr(str_shuffle(str_repeat(self::CODE_ALPHABET, self::CODE_LENGTH)), 0, self::CODE_LENGTH);
        } while (Lobby::where('code', $code)->exists());

        return $code;
    }
}
