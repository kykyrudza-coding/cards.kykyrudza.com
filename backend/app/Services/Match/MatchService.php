<?php

namespace App\Services\Match;

use App\Events\MatchUpdated;
use App\Game\Blackjack\BlackjackEngine;
use App\Game\Blackjack\BlackjackState;
use App\Game\Contracts\GameActionException;
use App\Game\Contracts\GameEngine;
use App\Game\Contracts\GameState;
use App\Game\Durak\DurakPlayer;
use App\Game\Durak\DurakState;
use App\Game\GameCatalog;
use App\Models\GameMatch;
use App\Models\Lobby;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\Achievement\AchievementService;
use Illuminate\Support\Facades\DB;

class MatchService
{
    private const array RELATIONS = ['matchPlayers.user', 'lobby'];

    public function __construct(private readonly AchievementService $achievements) {}

    public function createForLobby(Lobby $lobby): GameMatch
    {
        return DB::transaction(function () use ($lobby) {
            $match = GameMatch::create([
                'lobby_id' => $lobby->id,
                'game_type' => $lobby->game_type,
                'status' => 'active',
                'round_number' => 1,
                'state' => [],
                'version' => 0,
                'started_at' => now(),
            ]);

            foreach ($lobby->players()->orderBy('seat')->get() as $lobbyPlayer) {
                MatchPlayer::create([
                    'match_id' => $match->id,
                    'user_id' => $lobbyPlayer->user_id,
                    'seat' => $lobbyPlayer->seat,
                    'chips' => $lobby->starting_chips,
                    'status' => 'active',
                ]);
            }

            $matchPlayersForEngine = $match->matchPlayers()->orderBy('seat')->get()
                ->map(fn (MatchPlayer $mp) => ['user_id' => $mp->user_id, 'seat' => $mp->seat, 'chips' => $mp->chips])
                ->all();

            // Blackjack waits for everyone to confirm a bet before dealing;
            // Durak has no betting phase, so it deals immediately.
            $state = $match->game_type === 'blackjack'
                ? (new BlackjackEngine)->initial($matchPlayersForEngine)
                : $this->engineFor($match)->start(['players' => $matchPlayersForEngine]);

            $this->persist($match, $state);
            $this->syncPlayers($match, $state);

            return $match->fresh(self::RELATIONS);
        });
    }

    public function performAction(GameMatch $match, User $user, string $action, array $payload = []): GameMatch
    {
        return DB::transaction(function () use ($match, $user, $action, $payload) {
            $locked = GameMatch::query()->lockForUpdate()->findOrFail($match->id);

            abort_if($locked->status !== 'active', 422, 'Match is not active.');

            $state = $this->hydrateState($locked);
            $previousPhase = $state->phase;

            try {
                $state = $this->engineFor($locked)->handleAction($state, $user->id, $action, $payload);
            } catch (GameActionException $e) {
                abort($e->status, $e->getMessage());
            }

            $this->persist($locked, $state);
            $this->syncPlayers($locked, $state);

            if ($state instanceof BlackjackState) {
                if ($action === 'split') {
                    $this->achievements->recordSplit($user);
                }
                $this->achievements->afterBlackjackAction($locked, $previousPhase, $state);
            } elseif ($state instanceof DurakState) {
                $this->achievements->afterDurakAction($locked, $previousPhase, $state);
            }

            return $locked->fresh(self::RELATIONS);
        });
    }

    public function placeBet(GameMatch $match, User $user, int $amount, int $expectedRound): GameMatch
    {
        return DB::transaction(function () use ($match, $user, $amount, $expectedRound) {
            $locked = GameMatch::query()->lockForUpdate()->findOrFail($match->id);
            abort_if($locked->game_type !== 'blackjack', 422, 'Betting is not available for this game.');
            abort_if($locked->status !== 'active', 422, 'Match is not active.');
            abort_if($locked->round_number !== $expectedRound, 409, 'Round already advanced. Refresh the table.');
            $state = BlackjackState::fromArray($locked->state);
            abort_if($state->phase !== 'round_finished', 422, 'Wait for this round to finish.');
            $player = collect($state->players)->first(fn ($player) => $player->userId === $user->id);
            abort_unless($player, 403, 'You are not a participant in this match.');
            abort_if($player->status !== 'active' || $player->chips < 100, 422, 'Not enough chips for another round.');
            $betStep = $amount <= 1000 ? 100 : ($amount <= 10000 ? 500 : 1000);
            abort_if($amount < 100 || $amount % $betStep !== 0, 422, 'Bets use steps of 100 up to 1000, 500 up to 10000, and 1000 above that.');
            abort_if($amount > $player->chips, 422, 'Not enough chips for this bet.');
            // A confirmed bet is immutable; identical retries are idempotent.
            if (isset($state->confirmedBets[$user->id])) {
                abort_if($state->confirmedBets[$user->id] !== $amount, 409, 'Your bet is already confirmed.');

                return $locked->fresh(self::RELATIONS);
            }
            $state->confirmedBets[$user->id] = $amount;
            $eligible = collect($state->players)->filter(fn ($player) => $player->status === 'active' && $player->chips >= 100);
            $dealt = false;
            if ($eligible->every(fn ($player) => isset($state->confirmedBets[$player->userId]))) {
                $state = (new BlackjackEngine)->nextRound($state, 100, bets: $state->confirmedBets);
                $dealt = true;
            }
            $this->persist($locked, $state);
            $this->syncPlayers($locked, $state);

            // A dealer natural Blackjack settles the round immediately on
            // deal, with no further action ever passing through
            // performAction() — catch that instant-settle case here too.
            if ($dealt) {
                $this->achievements->afterBlackjackAction($locked, 'dealing', $state);
            }

            return $locked->fresh(self::RELATIONS);
        });
    }

    public function nextRound(GameMatch $match, User $host, ?int $expectedRound = null): GameMatch
    {
        // Old clients cannot bypass individual bet confirmation.
        abort_if($match->lobby->host_id !== $host->id, 403, 'Only the host can request the next round.');
        abort_if($expectedRound !== null && $match->round_number !== $expectedRound, 409, 'Round already advanced. Refresh the table.');
        abort(422, 'Every player must confirm a bet before the next deal.');
    }

    public function finish(GameMatch $match, User $host): GameMatch
    {
        return DB::transaction(function () use ($match, $host) {
            $locked = GameMatch::query()->lockForUpdate()->findOrFail($match->id);

            abort_if($locked->lobby->host_id !== $host->id, 403, 'Only the host can finish the match.');
            abort_if($locked->status !== 'active', 422, 'Match is already finished.');

            $locked->update(['status' => 'finished', 'finished_at' => now()]);

            $fresh = $locked->fresh(self::RELATIONS);
            MatchUpdated::dispatch($fresh);

            return $fresh;
        });
    }

    private function persist(GameMatch $match, GameState $state): void
    {
        $data = $state->toArray();

        $match->update([
            'state' => $data,
            'round_number' => $data['round'],
            'version' => $match->version + 1,
        ]);

        MatchUpdated::dispatch($match->fresh(self::RELATIONS));
    }

    private function syncPlayers(GameMatch $match, GameState $state): void
    {
        if ($state instanceof BlackjackState) {
            foreach ($state->players as $player) {
                MatchPlayer::where('match_id', $match->id)
                    ->where('user_id', $player->userId)
                    ->update(['chips' => $player->chips, 'status' => $player->status]);
            }

            return;
        }

        foreach ($state->players as $player) {
            /** @var DurakPlayer $player */
            MatchPlayer::where('match_id', $match->id)
                ->where('user_id', $player->userId)
                ->update(['status' => $player->status]);
        }
    }

    private function hydrateState(GameMatch $match): GameState
    {
        return GameCatalog::hydrateState($match->game_type, $match->state);
    }

    private function engineFor(GameMatch $match): GameEngine
    {
        return GameCatalog::engine($match->game_type);
    }
}
