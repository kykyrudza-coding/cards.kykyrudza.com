<?php

namespace Tests\Feature;

use App\Events\LobbyUpdated;
use App\Models\Lobby;
use App\Models\LobbyPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LobbyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_lobby_and_is_added_as_host(): void
    {
        $host = User::factory()->create();

        $response = $this->actingAs($host, 'sanctum')->postJson('/api/lobbies', [
            'game_type' => 'blackjack',
            'max_players' => 4,
            'starting_chips' => 5000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'waiting')
            ->assertJsonPath('host.id', $host->id)
            ->assertJsonCount(1, 'players')
            ->assertJsonPath('players.0.id', $host->id)
            ->assertJsonPath('players.0.is_host', true);

        $this->assertDatabaseHas('lobby_players', [
            'lobby_id' => $response->json('id'),
            'user_id' => $host->id,
            'seat' => 0,
        ]);
    }

    public function test_lobby_code_is_six_uppercase_characters(): void
    {
        $host = User::factory()->create();

        $response = $this->actingAs($host, 'sanctum')->postJson('/api/lobbies', []);

        $code = $response->json('code');

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $code);
    }

    public function test_second_user_can_join_lobby(): void
    {
        Event::fake([LobbyUpdated::class]);

        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = $this->createLobby($host);

        $response = $this->actingAs($guest, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/join");

        $response->assertOk()->assertJsonCount(2, 'players');

        Event::assertDispatched(LobbyUpdated::class, fn (LobbyUpdated $event) => $event->reason === 'player_joined');
    }

    public function test_user_cannot_join_lobby_twice(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = $this->createLobby($host);

        $this->actingAs($guest, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/join")->assertOk();
        $response = $this->actingAs($guest, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/join");

        $response->assertUnprocessable();
    }

    public function test_user_cannot_join_full_lobby(): void
    {
        $host = User::factory()->create();
        $lobby = $this->createLobby($host, ['max_players' => 1]);
        $guest = User::factory()->create();

        $response = $this->actingAs($guest, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/join");

        $response->assertUnprocessable();
    }

    public function test_player_can_toggle_ready(): void
    {
        $host = User::factory()->create();
        $lobby = $this->createLobby($host);

        $response = $this->actingAs($host, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/ready", [
            'ready' => true,
        ]);

        $response->assertOk()->assertJsonPath('players.0.is_ready', true);
    }

    public function test_non_host_cannot_start_lobby(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = $this->createLobby($host);
        $this->joinLobby($guest, $lobby);

        $response = $this->actingAs($guest, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/start");

        $response->assertForbidden();
    }

    public function test_lobby_cannot_start_if_somebody_is_not_ready(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = $this->createLobby($host);
        $this->joinLobby($guest, $lobby);

        $this->setReady($host, $lobby, true);

        $response = $this->actingAs($host, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/start");

        $response->assertUnprocessable();
    }

    public function test_host_can_start_lobby_when_everyone_is_ready(): void
    {
        $host = User::factory()->create();
        $lobby = $this->createLobby($host);
        $this->setReady($host, $lobby, true);

        $response = $this->actingAs($host, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/start");

        $response->assertCreated()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('game_type', 'blackjack')
            // A shuffled opening blackjack can legitimately settle immediately.
            ->assertJsonPath('game.phase', fn (string $phase) => in_array($phase, ['player_turn', 'round_finished'], true));

        $this->assertDatabaseHas('lobbies', ['id' => $lobby->id, 'status' => 'started']);
    }

    public function test_player_can_leave_lobby(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = $this->createLobby($host);
        $this->joinLobby($guest, $lobby);

        $response = $this->actingAs($guest, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/leave");

        $response->assertOk();
        $this->assertDatabaseMissing('lobby_players', ['lobby_id' => $lobby->id, 'user_id' => $guest->id]);
    }

    public function test_host_migrates_to_oldest_remaining_player_when_leaving(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = $this->createLobby($host);
        $this->joinLobby($guest, $lobby);

        $this->actingAs($host, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/leave")->assertOk();

        $this->assertDatabaseHas('lobbies', ['id' => $lobby->id, 'host_id' => $guest->id]);
    }

    public function test_lobby_closes_when_last_player_leaves(): void
    {
        $host = User::factory()->create();
        $lobby = $this->createLobby($host);

        $this->actingAs($host, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/leave")->assertOk();

        $this->assertDatabaseHas('lobbies', ['id' => $lobby->id, 'status' => 'closed']);
    }

    private function createLobby(User $host, array $overrides = []): Lobby
    {
        $lobby = Lobby::create(array_merge([
            'code' => strtoupper(substr(md5((string) $host->id.microtime()), 0, 6)),
            'host_id' => $host->id,
            'game_type' => 'blackjack',
            'status' => 'waiting',
            'max_players' => 4,
            'starting_chips' => 5000,
            'is_private' => false,
        ], $overrides));

        LobbyPlayer::create([
            'lobby_id' => $lobby->id,
            'user_id' => $host->id,
            'seat' => 0,
            'is_ready' => false,
            'joined_at' => now(),
        ]);

        return $lobby;
    }

    private function joinLobby(User $user, Lobby $lobby): void
    {
        $this->actingAs($user, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/join")->assertOk();
    }

    private function setReady(User $user, Lobby $lobby, bool $ready): void
    {
        $this->actingAs($user, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/ready", ['ready' => $ready])->assertOk();
    }
}
