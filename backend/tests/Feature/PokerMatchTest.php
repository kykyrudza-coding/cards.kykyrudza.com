<?php

namespace Tests\Feature;

use App\Models\Lobby;
use App\Models\LobbyPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PokerMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_poker_lobby_is_accepted_by_validation(): void
    {
        $host = User::factory()->create();

        $this->actingAs($host, 'sanctum')
            ->postJson('/api/lobbies', ['game_type' => 'poker', 'max_players' => 6, 'starting_chips' => 5000, 'default_bet' => 100])
            ->assertCreated()
            ->assertJsonPath('game_type', 'poker');
    }

    public function test_poker_needs_at_least_two_players_to_start(): void
    {
        $host = User::factory()->create();
        $lobby = $this->readyPokerLobby($host);

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/lobbies/{$lobby->code}/start")
            ->assertUnprocessable();
    }

    public function test_starting_a_poker_lobby_deals_hole_cards_and_posts_blinds(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = $this->readyPokerLobby($host, $guest);

        $response = $this->actingAs($host, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/start");

        $response->assertCreated()
            ->assertJsonPath('game_type', 'poker')
            ->assertJsonPath('game.phase', 'preflop')
            ->assertJsonPath('game.big_blind', 100)
            ->assertJsonPath('game.pot', 150)
            ->assertJsonCount(2, 'game.players')
            ->assertJsonCount(2, 'game.players.0.hand')
            ->assertJsonMissingPath('game.players.1.hand');

        $this->assertDatabaseHas('match_players', ['user_id' => $host->id, 'chips' => 4950]);
        $this->assertDatabaseHas('match_players', ['user_id' => $guest->id, 'chips' => 4900]);
    }

    public function test_betting_round_trip_over_http(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = $this->readyPokerLobby($host, $guest);

        $matchId = $this->actingAs($host, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/start")->json('id');

        // Heads-up: the dealer (host, seat 0) is the small blind and acts first.
        $this->actingAs($guest, 'sanctum')
            ->postJson("/api/matches/{$matchId}/actions/call")
            ->assertForbidden();

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$matchId}/actions/raise", ['amount' => 50])
            ->assertUnprocessable(); // below the minimum raise

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$matchId}/actions/raise", ['amount' => 300])
            ->assertOk()
            ->assertJsonPath('game.current_player_id', $guest->id)
            ->assertJsonPath('game.pot', 400);

        $this->actingAs($guest, 'sanctum')
            ->postJson("/api/matches/{$matchId}/actions/call")
            ->assertOk()
            ->assertJsonPath('game.phase', 'flop')
            ->assertJsonCount(3, 'game.community');

        $this->actingAs($guest, 'sanctum')
            ->postJson("/api/matches/{$matchId}/actions/fold")
            ->assertOk()
            ->assertJsonPath('game.phase', 'hand_finished')
            ->assertJsonPath('game.results.0.user_id', $host->id);

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$matchId}/actions/next-hand")
            ->assertOk()
            ->assertJsonPath('game.phase', 'hand_finished');

        $this->actingAs($guest, 'sanctum')
            ->postJson("/api/matches/{$matchId}/actions/next-hand")
            ->assertOk()
            ->assertJsonPath('game.phase', 'preflop')
            ->assertJsonPath('round', 2);
    }

    private function readyPokerLobby(User $host, User ...$guests): Lobby
    {
        $lobby = Lobby::create([
            'code' => strtoupper(substr(md5((string) $host->id.microtime()), 0, 6)),
            'host_id' => $host->id,
            'game_type' => 'poker',
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
}
