<?php

namespace Tests\Feature;

use App\Events\MatchUpdated;
use App\Game\Blackjack\BlackjackEngine;
use App\Game\Blackjack\Card;
use App\Game\Blackjack\Deck;
use App\Models\GameMatch;
use App\Models\Lobby;
use App\Models\LobbyPlayer;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\Match\MatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_ready_lobby_creates_an_active_match_awaiting_first_bets(): void
    {
        // The very first hand now goes through the same bet-confirmation
        // flow as every other round, instead of auto-dealing with the
        // lobby's default_bet before anyone has agreed to a stake.
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = $this->readyLobby($host, $guest);

        $response = $this->actingAs($host, 'sanctum')->postJson("/api/lobbies/{$lobby->code}/start");

        $response->assertCreated()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('game_type', 'blackjack')
            ->assertJsonPath('game.round', 0)
            ->assertJsonPath('game.phase', 'round_finished')
            ->assertJsonCount(2, 'game.players')
            ->assertJsonPath('game.players.0.hands', [])
            ->assertJsonPath('game.players.0.chips', $lobby->starting_chips);

        $this->assertDatabaseHas('matches', ['lobby_id' => $lobby->id, 'status' => 'active']);
        $this->assertDatabaseHas('lobbies', ['id' => $lobby->id, 'status' => 'started']);
        $this->assertDatabaseCount('match_players', 2);
    }

    public function test_first_hand_deals_once_every_player_confirms_a_bet(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = $this->readyLobby($host, $guest);
        $match = $this->actingAs($host, 'sanctum')
            ->postJson("/api/lobbies/{$lobby->code}/start")
            ->json();

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match['id']}/bet", ['amount' => 100, 'expected_round' => 0])
            ->assertOk()
            ->assertJsonPath('game.round', 0);

        $response = $this->actingAs($guest, 'sanctum')
            ->postJson("/api/matches/{$match['id']}/bet", ['amount' => 100, 'expected_round' => 0]);

        $response->assertOk()
            ->assertJsonPath('game.round', 1)
            ->assertJsonPath('game.players.0.hands.0.bet', 100);
    }

    public function test_match_show_requires_participation(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $outsider = User::factory()->create();
        $match = $this->deterministicMatch([$host, $guest], ['5', '7', '2', '6', '8', '3']);

        $this->actingAs($outsider, 'sanctum')->getJson("/api/matches/{$match->id}")->assertForbidden();
        $this->actingAs($host, 'sanctum')->getJson("/api/matches/{$match->id}")->assertOk();
    }

    public function test_only_the_current_player_can_hit(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $match = $this->deterministicMatch([$host, $guest], ['5', '7', '2', '6', '8', '3', '4']);

        // host (seat 0) is current; guest must be rejected
        $this->actingAs($guest, 'sanctum')
            ->postJson("/api/matches/{$match->id}/actions/hit")
            ->assertForbidden();

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/actions/hit")
            ->assertOk()
            ->assertJsonPath('game.players.0.hands.0.score', 15);
    }

    public function test_hit_broadcasts_match_updated_with_incremented_version(): void
    {
        Event::fake([MatchUpdated::class]);

        $host = User::factory()->create();
        $guest = User::factory()->create();
        $match = $this->deterministicMatch([$host, $guest], ['5', '7', '2', '6', '8', '3', '4']);

        $this->actingAs($host, 'sanctum')->postJson("/api/matches/{$match->id}/actions/hit")->assertOk();

        Event::assertDispatched(MatchUpdated::class, fn (MatchUpdated $e) => $e->match->id === $match->id && $e->match->version === 2);
    }

    public function test_stand_advances_turn_to_next_player(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $match = $this->deterministicMatch([$host, $guest], ['5', '7', '2', '6', '8', '3']);

        $response = $this->actingAs($host, 'sanctum')->postJson("/api/matches/{$match->id}/actions/stand");

        $response->assertOk()->assertJsonPath('game.current_player_id', $guest->id);
    }

    public function test_double_requires_exactly_two_cards(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $match = $this->deterministicMatch([$host, $guest], ['5', '7', '2', '6', '8', '3', '4']);

        $this->actingAs($host, 'sanctum')->postJson("/api/matches/{$match->id}/actions/hit")->assertOk();

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/actions/double")
            ->assertUnprocessable();
    }

    public function test_split_creates_two_hands(): void
    {
        $host = User::factory()->create();
        $match = $this->deterministicMatch([$host], ['8', '2', '8', '3', '3', 'K']);

        $response = $this->actingAs($host, 'sanctum')->postJson("/api/matches/{$match->id}/actions/split");

        $response->assertOk()->assertJsonCount(2, 'game.players.0.hands');
    }

    public function test_legacy_next_round_cannot_bypass_player_bets(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        // finished round: both stand, dealer resolves
        $match = $this->deterministicMatch([$host, $guest], ['10', '10', '7', '8', '9', '6', '5']);
        $this->actingAs($host, 'sanctum')->postJson("/api/matches/{$match->id}/actions/stand")->assertOk();
        $this->actingAs($guest, 'sanctum')->postJson("/api/matches/{$match->id}/actions/stand")->assertOk();

        $this->actingAs($guest, 'sanctum')
            ->postJson("/api/matches/{$match->id}/next-round")
            ->assertForbidden();

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/next-round")
            ->assertUnprocessable();
    }

    public function test_bet_rejects_replayed_round_number(): void
    {
        $host = User::factory()->create();
        $match = $this->deterministicMatch([$host], ['10', '9', '8', '8']);
        $this->actingAs($host, 'sanctum')->postJson("/api/matches/{$match->id}/actions/stand")->assertOk();
        $this->postJson("/api/matches/{$match->id}/bet", ['expected_round' => 1, 'amount' => 100])->assertOk()->assertJsonPath('round', 2);
        $this->postJson("/api/matches/{$match->id}/bet", ['expected_round' => 1, 'amount' => 100])->assertConflict();
        $this->assertSame(2, $match->fresh()->round_number);
    }

    public function test_result_profit_and_dealer_status_are_authoritative_and_persisted(): void
    {
        $host = User::factory()->create();
        $match = $this->deterministicMatch([$host], ['10', '9', '9', '8']);
        $this->actingAs($host, 'sanctum')->getJson("/api/matches/{$match->id}")
            ->assertJsonPath('game.players.0.hands.0.profit', null)
            ->assertJsonPath('game.dealer.status', null)
            ->assertJsonPath('default_bet', 100)
            ->assertJsonPath('manual_bets', true);
        $this->postJson("/api/matches/{$match->id}/actions/stand")
            ->assertOk()->assertJsonPath('game.players.0.hands.0.profit', 100)
            ->assertJsonPath('game.players.0.chips', 5100)
            ->assertJsonPath('game.dealer.status', 'stood');
        $this->getJson("/api/matches/{$match->id}")->assertJsonPath('game.players.0.hands.0.profit', 100);
    }

    public function test_only_host_can_finish_match(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $match = $this->deterministicMatch([$host, $guest], ['5', '7', '2', '6', '8', '3']);

        $this->actingAs($guest, 'sanctum')
            ->postJson("/api/matches/{$match->id}/finish")
            ->assertForbidden();

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/finish")
            ->assertOk()
            ->assertJsonPath('status', 'finished');

        $this->assertDatabaseHas('matches', ['id' => $match->id, 'status' => 'finished']);
    }

    public function test_two_sequential_actions_on_stale_instances_do_not_corrupt_state(): void
    {
        // Simulates two clients each holding a pre-fetched (now stale) Match
        // instance. The service must always re-fetch + lock by id, so both
        // actions apply cleanly in sequence instead of clobbering each other.
        $host = User::factory()->create();
        $guest = User::factory()->create();
        // host deals to [5,6]=11, hits 'K' -> 21 (auto-stood, turn advances to guest);
        // guest stands -> dealer [2,3] draws '9','5' to reach 19
        $match = $this->deterministicMatch([$host, $guest], ['5', '7', '2', '6', '8', '3', 'K', '9', '5']);

        $staleForHost = GameMatch::find($match->id);
        $staleForGuest = GameMatch::find($match->id);

        $service = app(MatchService::class);
        $service->performAction($staleForHost, $host, 'hit');
        $updated = $service->performAction($staleForGuest, $guest, 'stand');

        $this->assertSame(3, $updated->version);
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'version' => 3]);
    }

    public function test_each_player_confirms_individual_bet_before_single_atomic_deal(): void
    {
        Event::fake([MatchUpdated::class]);
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $match = $this->deterministicMatch([$host, $guest], ['10', '10', '7', '8', '9', '6', '5']);
        $this->actingAs($host, 'sanctum')->postJson("/api/matches/{$match->id}/actions/stand")->assertOk();
        $this->actingAs($guest, 'sanctum')->postJson("/api/matches/{$match->id}/actions/stand")->assertOk();
        $chips = $match->fresh()->state['players'][0]['chips'];
        $this->actingAs($host, 'sanctum')->postJson("/api/matches/{$match->id}/bet", ['expected_round' => 1, 'amount' => 1000])
            ->assertOk()->assertJsonPath('round', 1)->assertJsonPath("confirmed_bets.{$host->id}", 1000)
            ->assertJsonPath('game.players.0.chips', $chips);
        $version = $match->fresh()->version;
        $this->postJson("/api/matches/{$match->id}/bet", ['expected_round' => 1, 'amount' => 1000])->assertOk();
        $this->assertSame($version, $match->fresh()->version);
        $this->postJson("/api/matches/{$match->id}/bet", ['expected_round' => 1, 'amount' => 1500])->assertConflict();
        $this->actingAs($guest, 'sanctum')->postJson("/api/matches/{$match->id}/bet", ['expected_round' => 1, 'amount' => 1500])
            ->assertOk()->assertJsonPath('round', 2)->assertJsonPath('game.players.0.hands.0.bet', 1000)
            ->assertJsonPath('game.players.1.hands.0.bet', 1500);
        $this->assertSame([], $match->fresh()->state['confirmed_bets']);
        Event::assertDispatched(MatchUpdated::class, fn ($event) => $event->match->round_number === 2);
    }

    public function test_bet_validates_steps_balance_phase_and_participation(): void
    {
        $host = User::factory()->create();
        $outsider = User::factory()->create();
        $match = $this->deterministicMatch([$host], ['10', '9', '9', '8']);
        $url = "/api/matches/{$match->id}/bet";
        $this->actingAs($outsider, 'sanctum')->postJson($url, ['expected_round' => 1, 'amount' => 100])->assertForbidden();
        $this->actingAs($host, 'sanctum')->postJson($url, ['expected_round' => 1, 'amount' => 100])->assertUnprocessable();
        $this->postJson("/api/matches/{$match->id}/actions/stand")->assertOk();
        foreach ([0, 50, 150, 1100, 1250, 6000] as $amount) {
            $this->postJson($url, ['expected_round' => 1, 'amount' => $amount])->assertUnprocessable();
        }
        $this->assertSame([], $match->fresh()->state['confirmed_bets']);
        $this->assertSame(1, $match->fresh()->round_number);
        $this->postJson($url, ['amount' => 100])->assertUnprocessable();
        $this->postJson($url, ['expected_round' => 2, 'amount' => 100])->assertConflict();
    }

    public function test_broke_player_does_not_block_next_deal(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $match = $this->deterministicMatch([$host, $guest], ['10', '10', '7', '8', '9', '6', '5']);
        $this->actingAs($host, 'sanctum')->postJson("/api/matches/{$match->id}/actions/stand")->assertOk();
        $this->actingAs($guest, 'sanctum')->postJson("/api/matches/{$match->id}/actions/stand")->assertOk();
        $state = $match->fresh()->state;
        $state['players'][1]['chips'] = 50;
        $match->update(['state' => $state]);
        $this->postJson("/api/matches/{$match->id}/bet", ['expected_round' => 1, 'amount' => 100])->assertUnprocessable();
        $this->actingAs($host, 'sanctum')->postJson("/api/matches/{$match->id}/bet", ['expected_round' => 1, 'amount' => 100])
            ->assertOk()->assertJsonPath('round', 2)->assertJsonPath('game.players.1.status', 'out');
    }

    public function test_jack_king_and_ten_queen_can_split_by_value(): void
    {
        foreach ([['J', 'K'], ['10', 'Q']] as [$first, $second]) {
            $host = User::factory()->create();
            $match = $this->deterministicMatch([$host], [$first, '2', $second, '3', '3', '4']);
            $this->actingAs($host, 'sanctum')->getJson("/api/matches/{$match->id}")->assertJsonFragment(['allowed_actions' => ['hit', 'stand', 'double', 'split']]);
            $this->postJson("/api/matches/{$match->id}/actions/split")->assertOk()->assertJsonCount(2, 'game.players.0.hands')
                ->assertJsonPath('game.players.0.hands.0.cards.0.rank', $first)->assertJsonPath('game.players.0.hands.1.cards.0.rank', $second);
        }
    }

    /**
     * @param  User[]  $users
     */
    private function readyLobby(User $host, User ...$guests): Lobby
    {
        $lobby = Lobby::create([
            'code' => strtoupper(substr(md5((string) $host->id.microtime()), 0, 6)),
            'host_id' => $host->id,
            'game_type' => 'blackjack',
            'status' => 'waiting',
            'max_players' => 7,
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
     * @param  string[]  $ranks
     */
    private function deterministicMatch(array $users, array $ranks, int $bet = 100): GameMatch
    {
        $host = $users[0];
        $lobby = $this->readyLobby($host, ...array_slice($users, 1));
        $lobby->update(['status' => 'started']);

        $match = GameMatch::create([
            'lobby_id' => $lobby->id,
            'game_type' => 'blackjack',
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
            $players[] = ['user_id' => $user->id, 'seat' => $i, 'chips' => 5000];
        }

        $deck = Deck::fromCards(array_map(fn (string $r) => new Card($r, 'spades'), $ranks));
        $state = (new BlackjackEngine)->deal($players, $bet, 1, $deck);

        $match->update(['state' => $state->toArray()]);

        foreach ($state->players as $sp) {
            MatchPlayer::where('match_id', $match->id)->where('user_id', $sp->userId)->update(['chips' => $sp->chips]);
        }

        return $match->fresh();
    }
}
