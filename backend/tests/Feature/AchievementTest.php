<?php

namespace Tests\Feature;

use App\Game\Blackjack\BlackjackHand;
use App\Game\Blackjack\BlackjackPlayer;
use App\Game\Blackjack\BlackjackState;
use App\Game\Blackjack\Card as BlackjackCard;
use App\Game\Blackjack\Deck as BlackjackDeck;
use App\Game\Durak\Card as DurakCard;
use App\Game\Durak\Deck as DurakDeck;
use App\Game\Durak\DurakEngine;
use App\Game\Durak\DurakPlayer;
use App\Game\Durak\DurakState;
use App\Models\GameMatch;
use App\Models\Lobby;
use App\Models\LobbyPlayer;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Models\UserStatistic;
use App\Services\Achievement\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    // --- AchievementService: Blackjack -----------------------------------

    public function test_a_won_hand_grants_first_win_and_updates_the_streak(): void
    {
        $user = User::factory()->create();
        $match = $this->blackjackMatch();
        $service = app(AchievementService::class);

        $state = $this->blackjackState([
            new BlackjackPlayer($user->id, 0, 900, 'active', [
                new BlackjackHand([new BlackjackCard('K', 'clubs'), new BlackjackCard('9', 'clubs')], 100, 'stood', 'win', profit: 100),
            ]),
        ]);

        $service->afterBlackjackAction($match, 'player_turn', $state);

        $stat = UserStatistic::where('user_id', $user->id)->first();
        $this->assertSame(1, $stat->blackjack_hands_played);
        $this->assertSame(1, $stat->blackjack_hands_won);
        $this->assertSame(1, $stat->current_win_streak);
        $this->assertTrue($user->achievements()->where('key', 'first_win')->exists());
    }

    public function test_a_natural_blackjack_grants_the_blackjack_achievement(): void
    {
        $user = User::factory()->create();
        $match = $this->blackjackMatch();
        $service = app(AchievementService::class);

        $state = $this->blackjackState([
            new BlackjackPlayer($user->id, 0, 1150, 'active', [
                new BlackjackHand([new BlackjackCard('A', 'clubs'), new BlackjackCard('K', 'clubs')], 100, 'blackjack', 'blackjack', profit: 150),
            ]),
        ]);

        $service->afterBlackjackAction($match, 'player_turn', $state);

        $this->assertSame(1, UserStatistic::where('user_id', $user->id)->first()->blackjacks_hit);
        $this->assertTrue($user->achievements()->where('key', 'natural_blackjack')->exists());
    }

    public function test_a_high_stakes_win_grants_high_roller(): void
    {
        $user = User::factory()->create();
        $match = $this->blackjackMatch();
        $service = app(AchievementService::class);

        $state = $this->blackjackState([
            new BlackjackPlayer($user->id, 0, 3000, 'active', [
                new BlackjackHand([new BlackjackCard('K', 'clubs'), new BlackjackCard('9', 'clubs')], 1000, 'stood', 'win', profit: 1000),
            ]),
        ]);

        $service->afterBlackjackAction($match, 'player_turn', $state);

        $this->assertTrue($user->achievements()->where('key', 'high_roller')->exists());
    }

    public function test_winning_because_the_dealer_busted_is_witnessed(): void
    {
        $user = User::factory()->create();
        $match = $this->blackjackMatch();
        $service = app(AchievementService::class);

        $state = $this->blackjackState([
            new BlackjackPlayer($user->id, 0, 1100, 'active', [
                new BlackjackHand([new BlackjackCard('9', 'clubs'), new BlackjackCard('8', 'clubs')], 100, 'stood', 'win', profit: 100),
            ]),
        ], dealerCards: [new BlackjackCard('K', 'spades'), new BlackjackCard('Q', 'spades'), new BlackjackCard('5', 'spades')]);

        $service->afterBlackjackAction($match, 'player_turn', $state);

        $this->assertSame(1, UserStatistic::where('user_id', $user->id)->first()->dealer_busts_witnessed);
    }

    public function test_a_lost_hand_resets_the_win_streak(): void
    {
        $user = User::factory()->create();
        UserStatistic::create(['user_id' => $user->id, 'current_win_streak' => 4, 'best_win_streak' => 4]);
        $match = $this->blackjackMatch();
        $service = app(AchievementService::class);

        $state = $this->blackjackState([
            new BlackjackPlayer($user->id, 0, 900, 'active', [
                new BlackjackHand([new BlackjackCard('K', 'clubs'), new BlackjackCard('9', 'clubs'), new BlackjackCard('5', 'clubs')], 100, 'bust', 'lose'),
            ]),
        ]);

        $service->afterBlackjackAction($match, 'player_turn', $state);

        $stat = UserStatistic::where('user_id', $user->id)->first();
        $this->assertSame(0, $stat->current_win_streak);
        $this->assertSame(4, $stat->best_win_streak);
    }

    public function test_only_fires_once_per_round_transition(): void
    {
        $user = User::factory()->create();
        $match = $this->blackjackMatch();
        $service = app(AchievementService::class);

        $state = $this->blackjackState([
            new BlackjackPlayer($user->id, 0, 900, 'active', [
                new BlackjackHand([new BlackjackCard('K', 'clubs'), new BlackjackCard('9', 'clubs')], 100, 'stood', 'win', profit: 100),
            ]),
        ]);

        // previousPhase already 'round_finished' -> nothing new happened, skip.
        $service->afterBlackjackAction($match, 'round_finished', $state);

        $this->assertNull(UserStatistic::where('user_id', $user->id)->first());
    }

    // --- AchievementService: split -----------------------------------------

    public function test_five_splits_unlocks_split_master(): void
    {
        $user = User::factory()->create();
        $service = app(AchievementService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->recordSplit($user);
        }

        $this->assertSame(5, UserStatistic::where('user_id', $user->id)->first()->splits_performed);
        $this->assertTrue($user->achievements()->where('key', 'split_master')->exists());
    }

    // --- AchievementService: Durak -------------------------------------------

    public function test_durak_survivors_and_the_loser_are_credited_correctly(): void
    {
        $survivor = User::factory()->create();
        $loser = User::factory()->create();
        $match = $this->durakMatch();
        $service = app(AchievementService::class);

        $state = $this->durakState([
            new DurakPlayer($survivor->id, 0, 'safe', []),
            new DurakPlayer($loser->id, 1, 'active', [new DurakCard('7', 'clubs')]),
        ], loserId: $loser->id);

        $service->afterDurakAction($match, 'attack', $state);

        $survivorStat = UserStatistic::where('user_id', $survivor->id)->first();
        $loserStat = UserStatistic::where('user_id', $loser->id)->first();

        $this->assertSame(1, $survivorStat->durak_matches_survived);
        $this->assertSame(1, $survivorStat->current_win_streak);
        $this->assertTrue($survivor->achievements()->where('key', 'first_win')->exists());

        $this->assertSame(1, $loserStat->durak_losses);
        $this->assertSame(0, $loserStat->current_win_streak);
        $this->assertFalse($loser->achievements()->where('key', 'first_win')->exists());
    }

    // --- End-to-end over HTTP: MatchService really calls the service -------

    public function test_http_round_settlement_awards_achievements_for_both_players(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        // draw order: P1c1,P2c1,D1,P1c2,P2c2,D2, then dealer hits twice and busts.
        $match = $this->deterministicMatch([$host, $guest], ['A', '5', '2', 'K', '6', '3', '9', '8']);

        // host (seat 0) already has a natural Blackjack and is skipped;
        // guest (seat 1, score 11) is current and stands, settling the round.
        $this->actingAs($guest, 'sanctum')
            ->postJson("/api/matches/{$match->id}/actions/stand")
            ->assertOk()
            ->assertJsonPath('game.phase', 'round_finished');

        $this->assertTrue($host->achievements()->where('key', 'natural_blackjack')->exists());
        $this->assertTrue($host->achievements()->where('key', 'first_win')->exists());
        $this->assertTrue($guest->achievements()->where('key', 'first_win')->exists());

        $stats = $this->actingAs($guest, 'sanctum')->getJson('/api/statistics')->json();
        $this->assertSame(1, $stats['blackjack']['won']);
    }

    public function test_split_over_http_immediately_records_a_split_without_waiting_for_the_round(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        // P1c1=8,P2c1=x,D1,P1c2=8,P2c2=x,D2 -> host has a pair of 8s; '4','7'
        // are drawn by the two resulting split hands.
        $match = $this->deterministicMatch([$host, $guest], ['8', '5', '2', '8', '6', '3', '4', '7']);

        $this->actingAs($host, 'sanctum')
            ->postJson("/api/matches/{$match->id}/actions/split")
            ->assertOk();

        $this->assertSame(1, UserStatistic::where('user_id', $host->id)->first()->splits_performed);
    }

    // --- Achievements API ----------------------------------------------------

    public function test_unseen_achievements_are_only_surfaced_once(): void
    {
        $user = User::factory()->create();
        app(AchievementService::class)->recordSplit($user);
        for ($i = 0; $i < 4; $i++) {
            app(AchievementService::class)->recordSplit($user);
        }
        $this->assertTrue($user->achievements()->where('key', 'split_master')->exists());

        $first = $this->actingAs($user, 'sanctum')->getJson('/api/achievements/unseen')->json();
        $this->assertCount(1, $first);
        $this->assertSame('split_master', $first[0]['key']);

        $second = $this->actingAs($user, 'sanctum')->getJson('/api/achievements/unseen')->json();
        $this->assertCount(0, $second);
    }

    public function test_achievements_catalog_marks_locked_and_unlocked(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/achievements');

        $response->assertOk()->assertJsonCount(10, 'achievements');
        $this->assertNull(collect($response->json('achievements'))->firstWhere('key', 'first_win')['unlocked_at']);
    }

    // --- Profile ---------------------------------------------------------------

    public function test_updating_the_profile_changes_the_username(): void
    {
        $user = User::factory()->create(['username' => 'old_name']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', ['username' => 'new_name'])
            ->assertOk()
            ->assertJsonPath('username', 'new_name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => 'new_name']);
    }

    public function test_cannot_take_another_users_username(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create(['username' => 'mine']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', ['username' => 'taken'])
            ->assertUnprocessable();
    }

    // --- helpers ---------------------------------------------------------------

    /**
     * @param  BlackjackPlayer[]  $players
     * @param  BlackjackCard[]  $dealerCards
     */
    private function blackjackState(array $players, array $dealerCards = [], string $phase = 'round_finished'): BlackjackState
    {
        return new BlackjackState(
            phase: $phase,
            deck: BlackjackDeck::fromCards([]),
            dealerCards: $dealerCards !== [] ? $dealerCards : [new BlackjackCard('10', 'spades'), new BlackjackCard('7', 'spades')],
            dealerHoleHidden: false,
            players: $players,
            currentPlayerIndex: null,
            currentHandIndex: null,
            round: 1,
        );
    }

    /**
     * @param  DurakPlayer[]  $players
     */
    private function durakState(array $players, ?int $loserId): DurakState
    {
        return new DurakState(
            phase: 'finished',
            deck: DurakDeck::fromCards([]),
            trumpSuit: 'spades',
            trumpCard: new DurakCard('6', 'spades'),
            players: $players,
            table: [],
            attackerIndex: 0,
            defenderIndex: 1,
            attackLimit: 6,
            round: 3,
            loserId: $loserId,
        );
    }

    private function blackjackMatch(): GameMatch
    {
        $host = User::factory()->create();
        $lobby = $this->readyLobby($host);
        $lobby->update(['status' => 'started']);

        return GameMatch::create([
            'lobby_id' => $lobby->id,
            'game_type' => 'blackjack',
            'status' => 'active',
            'round_number' => 1,
            'state' => [],
            'version' => 1,
            'started_at' => now(),
        ]);
    }

    private function durakMatch(): GameMatch
    {
        $host = User::factory()->create();
        $lobby = Lobby::create([
            'code' => strtoupper(substr(md5((string) $host->id.microtime()), 0, 6)),
            'host_id' => $host->id,
            'game_type' => 'durak',
            'status' => 'started',
            'max_players' => 6,
            'starting_chips' => 5000,
            'default_bet' => 100,
            'is_private' => false,
        ]);
        LobbyPlayer::create([
            'lobby_id' => $lobby->id, 'user_id' => $host->id, 'seat' => 0, 'is_ready' => true, 'joined_at' => now(),
        ]);

        return GameMatch::create([
            'lobby_id' => $lobby->id,
            'game_type' => 'durak',
            'status' => 'active',
            'round_number' => 1,
            'state' => [],
            'version' => 1,
            'started_at' => now(),
        ]);
    }

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

        $deck = BlackjackDeck::fromCards(array_map(fn (string $r) => new BlackjackCard($r, 'spades'), $ranks));
        $state = (new \App\Game\Blackjack\BlackjackEngine)->deal($players, $bet, 1, $deck);

        $match->update(['state' => $state->toArray()]);

        foreach ($state->players as $sp) {
            MatchPlayer::where('match_id', $match->id)->where('user_id', $sp->userId)->update(['chips' => $sp->chips]);
        }

        return $match->fresh();
    }
}
