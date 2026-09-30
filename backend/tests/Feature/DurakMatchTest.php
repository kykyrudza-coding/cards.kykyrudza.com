<?php

namespace Tests\Feature;

use App\Game\Durak\Card;
use App\Game\Durak\Deck;
use App\Game\Durak\DurakEngine;
use App\Models\GameMatch;
use App\Models\Lobby;
use App\Models\LobbyPlayer;
use App\Models\MatchPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DurakMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_ready_durak_lobby_deals_six_cards_immediately(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = $this->readyDurakLobby($host, $guest);

        $response = $this->actingAs($host, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/start");

        $response->assertCreated()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('game_type', 'durak')
            ->assertJsonPath('game.phase', 'attack')
            ->assertJsonCount(2, 'game.players')
            ->assertJsonCount(6, 'game.players.0.hand');

        // Betting fields are Blackjack-only and shouldn't leak into a Durak response.
        $response->assertJsonMissingPath('default_bet');

        $this->assertDatabaseHas('matches', ['lobby_id' => $lobby->id, 'status' => 'active', 'game_type' => 'durak']);
    }

    public function test_creating_a_durak_lobby_is_accepted_by_validation(): void
    {
        $host = User::factory()->create();

        $this->actingAs($host, 'sanctum')
            ->postJson('/api/lobbies', ['game_type' => 'durak', 'max_players' => 3])
            ->assertCreated()
            ->assertJsonPath('game_type', 'durak');
    }

    public function test_attack_and_defend_round_trip_over_http(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();

        $cards = [];
        foreach (['7', '8', '9', '10', 'J', 'Q'] as $rank) {
            $cards[] = new Card($rank, 'clubs'); // host
            $cards[] = new Card($rank, 'diamonds'); // guest
        }
        $cards[] = new Card('6', 'hearts'); // trump

        $match = $this->deterministicDurakMatch([$host, $guest], $cards);

        // host is attacker (holds no trump; falls back to random — pin the
        // rotation instead of relying on it, since only the flow matters here).
        $state = $match->fresh()->state;
        $attackerUserId = $state['players'][$state['attacker_index']]['user_id'];
        $attacker = $attackerUserId === $host->id ? $host : $guest;
        $defender = $attacker->id === $host->id ? $guest : $host;
        $attackCard = $state['players'][$state['attacker_index']]['hand'][0];

        $this->actingAs($attacker, 'sanctum')
            ->postJson("/api/matches/{$match->id}/actions/attack", ['cards' => [$attackCard]])
            ->assertOk()
            ->assertJsonCount(1, 'game.table');

        $this->actingAs($defender, 'sanctum')
            ->postJson("/api/matches/{$match->id}/actions/defend", [
                'attack' => $attackCard,
                'defense' => ['rank' => '6', 'suit' => 'hearts'],
            ])
            ->assertUnprocessable(); // defender doesn't hold the trump in this fixture — proves illegal moves are rejected server-side
    }

    public function test_cannot_attack_out_of_turn_over_http(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();

        $cards = [];
        foreach (['7', '8', '9', '10', 'J', 'Q'] as $rank) {
            $cards[] = new Card($rank, 'clubs');
            $cards[] = new Card($rank, 'diamonds');
        }
        $cards[] = new Card('6', 'hearts');

        $match = $this->deterministicDurakMatch([$host, $guest], $cards);
        $state = $match->fresh()->state;
        $defenderUserId = $state['players'][$state['defender_index']]['user_id'];
        $defender = $defenderUserId === $host->id ? $host : $guest;

        $this->actingAs($defender, 'sanctum')
            ->postJson("/api/matches/{$match->id}/actions/attack", ['cards' => [['rank' => '7', 'suit' => 'clubs']]])
            ->assertForbidden();
    }

    private function readyDurakLobby(User $host, User ...$guests): Lobby
    {
        $lobby = Lobby::create([
            'code' => strtoupper(substr(md5((string) $host->id.microtime()), 0, 6)),
            'host_id' => $host->id,
            'game_type' => 'durak',
            'status' => 'waiting',
            'max_players' => 6,
            'starting_chips' => 5000,
            'default_bet' => 100,
            'is_private' => false,
        ]);

        LobbyPlayer::create([
            'lobby_id' => $lobby->id, 'user_id' => $host->id, 'seat' => 0, 'is_ready' => true, 'joined_at' => now(),
        ]);

        foreach (array_values($guests) as $i => $guest) {
            LobbyPlayer::create([
                'lobby_id' => $lobby->id, 'user_id' => $guest->id, 'seat' => $i + 1, 'is_ready' => true, 'joined_at' => now(),
            ]);
        }

        return $lobby;
    }

    /**
     * @param  User[]  $users
     * @param  Card[]  $cards  Drawn in order: 6 per player round-robin, then the trump.
     */
    private function deterministicDurakMatch(array $users, array $cards): GameMatch
    {
        $host = $users[0];
        $lobby = $this->readyDurakLobby($host, ...array_slice($users, 1));
        $lobby->update(['status' => 'started']);

        $match = GameMatch::create([
            'lobby_id' => $lobby->id,
            'game_type' => 'durak',
            'status' => 'active',
            'round_number' => 1,
            'state' => [],
            'version' => 1,
            'started_at' => now(),
        ]);

        $players = [];
        foreach ($users as $i => $user) {
            MatchPlayer::create([
                'match_id' => $match->id, 'user_id' => $user->id, 'seat' => $i, 'chips' => 5000, 'status' => 'active',
            ]);
            $players[] = ['user_id' => $user->id, 'seat' => $i];
        }

        $state = (new DurakEngine)->deal($players, Deck::fromCards($cards));

        $match->update(['state' => $state->toArray()]);

        return $match->fresh();
    }
}
