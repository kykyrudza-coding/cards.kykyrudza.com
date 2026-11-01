<?php

namespace Tests\Unit\Poker;

use App\Game\Poker\Card;
use App\Game\Poker\Deck;
use App\Game\Poker\PokerActionException;
use App\Game\Poker\PokerEngine;
use App\Game\Poker\PokerState;
use PHPUnit\Framework\TestCase;

/**
 * The table-flow features that make a hand watchable: action log, turn
 * clock, staged all-in runouts, showdown details and showdown pacing.
 */
class PokerFlowTest extends TestCase
{
    private PokerEngine $engine;

    private int $now = 1_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = 1_000_000;
        $this->engine = new PokerEngine(fn (): int => $this->now);
    }

    /**
     * @return Card[]
     */
    private function cards(string $spec): array
    {
        $suits = ['c' => 'clubs', 'd' => 'diamonds', 'h' => 'hearts', 's' => 'spades'];

        return array_map(
            fn (string $c) => new Card(substr($c, 0, -1), $suits[substr($c, -1)]),
            explode(' ', $spec)
        );
    }

    /**
     * @param  int[]  $chips
     */
    private function deal(array $chips, string $deck): PokerState
    {
        $players = [];
        foreach ($chips as $i => $stack) {
            $players[] = ['user_id' => $i + 1, 'seat' => $i, 'chips' => $stack];
        }
        $filler = $this->cards('2d 3d 4d 5d 6d 7d 8d 9d 10d Jd Qd Kd Ad');

        return $this->engine->deal($players, 100, Deck::fromCards([...$this->cards($deck), ...$filler]));
    }

    private function act(PokerState $state, int $userId, string $action, array $payload = []): PokerState
    {
        return $this->engine->applyAction($state, $userId, $action, $payload);
    }

    public function test_actions_are_logged_and_shown_as_each_players_last_action(): void
    {
        $state = $this->deal([1000, 1000, 1000], '2c 3c 4c 5c 6c 7c');

        $this->assertSame(['small_blind', 'big_blind'], array_column($state->log, 'action'));

        $state = $this->act($state, 1, 'raise', ['amount' => 300]);
        $state = $this->act($state, 2, 'fold');
        $state = $this->act($state, 3, 'call');

        $this->assertSame(['small_blind', 'big_blind', 'raise', 'fold', 'call', 'street'], array_column($state->log, 'action'));
        $this->assertSame('flop', end($state->log)['phase']);

        $view = $this->engine->publicState($state, 1);
        $this->assertSame('flop', $view['phase']);
        // A new street clears the "last action" captions.
        $this->assertNull($view['players'][0]['last_action']);
    }

    public function test_last_action_labels_distinguish_bet_raise_and_all_in(): void
    {
        $state = $this->deal([1000, 1000, 1000], '2c 3c 4c 5c 6c 7c');
        $state = $this->act($state, 1, 'call');
        $state = $this->act($state, 2, 'call');
        $state = $this->act($state, 3, 'check');

        $state = $this->act($state, 2, 'raise', ['amount' => 200]); // first bet on the flop
        $this->assertSame('bet', $state->players[1]->lastAction);
        $state = $this->act($state, 3, 'raise', ['amount' => 500]);
        $this->assertSame('raise', $state->players[2]->lastAction);
        $state = $this->act($state, 1, 'all_in');
        $this->assertSame('all_in', $state->players[0]->lastAction);
    }

    public function test_the_turn_clock_runs_and_folds_or_checks_for_an_idle_player(): void
    {
        $state = $this->deal([1000, 1000, 1000], '2c 3c 4c 5c 6c 7c');
        $this->assertSame($this->now + 45, $state->turnDeadline);

        try {
            $this->act($state, 2, 'timeout');
            $this->fail('A running clock cannot be timed out.');
        } catch (PokerActionException) {
            // expected
        }

        // User 1 faces the big blind: an idle player folds.
        $this->now += 46;
        $state = $this->act($state, 2, 'timeout');
        $this->assertSame('folded', $state->players[0]->status);
        $this->assertTrue(end($state->log)['auto']);
        $this->assertSame($this->now + 45, $state->turnDeadline); // the next player's clock

        // Big blind has nothing to call: an idle player is checked instead.
        $state = $this->act($state, 2, 'call');
        $this->now += 46;
        $state = $this->act($state, 1, 'timeout');
        $checks = array_values(array_filter($state->log, fn ($e) => $e['action'] === 'check'));
        $this->assertTrue($checks[0]['auto']);
        $this->assertSame('flop', $state->phase);
    }

    public function test_all_in_runout_remembers_how_much_board_was_out_and_paces_the_next_hand(): void
    {
        // u1 (dealer, Ah Ad) vs u2 (Kh Kd), preflop all-in: the whole board is still to come.
        $state = $this->deal([2000, 2000], 'Ah Kh Ad Kd 2c 7d 9s Jc 3h');
        $state = $this->act($state, 1, 'all_in');
        $state = $this->act($state, 2, 'call');

        $this->assertContains($state->phase, ['hand_finished', 'finished']);
        $this->assertSame(0, $state->runoutFrom);
        $this->assertTrue($state->showdown);
        $this->assertCount(5, $state->community);
        // 3 streets still to reveal, so the table gets longer to watch than after a river showdown.
        $this->assertGreaterThanOrEqual(9, $state->nextHandAt - $state->handEndedAt);

        // Both hands are public, with the made-hand names and the winning five.
        $view = $this->engine->publicState($state, 2);
        $this->assertSame('pair', $view['players'][0]['hand_name']);
        $this->assertCount(5, $view['results'][0]['cards']);
        $this->assertSame(0, $view['runout_from']);
    }

    public function test_betting_closed_on_the_flop_leaves_only_turn_and_river_to_reveal(): void
    {
        $state = $this->deal([2000, 2000], 'Ah Kh Ad Kd 2c 7d 9s Jc 3h');
        $state = $this->act($state, 1, 'call');
        $state = $this->act($state, 2, 'check');
        $this->assertSame('flop', $state->phase);

        $state = $this->act($state, 2, 'all_in');
        $state = $this->act($state, 1, 'call');

        $this->assertSame(3, $state->runoutFrom);
        $this->assertCount(5, $state->community);
    }

    public function test_a_fold_win_is_quick_and_reveals_nothing(): void
    {
        $state = $this->deal([1000, 1000], '2c 3c 4c 5c 6d 7d 8h 9h Ts');
        $state = $this->act($state, 1, 'fold');

        $this->assertFalse($state->showdown);
        $this->assertNull($state->runoutFrom);
        $this->assertLessThanOrEqual(3, $state->nextHandAt - $state->handEndedAt);
        $view = $this->engine->publicState($state, 2);
        $this->assertArrayNotHasKey('hand', $view['players'][0]);
        $this->assertSame([], $view['results'][0]['cards']);
    }

    public function test_a_stalled_table_is_dealt_on_after_the_grace_period(): void
    {
        $state = $this->deal([1000, 1000, 1000], '2c 3c 4c 5c 6c 7c');
        $state = $this->act($state, 1, 'fold');
        $state = $this->act($state, 2, 'fold');
        $this->assertSame('hand_finished', $state->phase);

        $this->now += 10;
        try {
            $this->act($state, 1, 'timeout');
            $this->fail('Too early to force the next hand.');
        } catch (PokerActionException) {
            // expected
        }

        $this->now += 30;
        $state = $this->act($state, 1, 'timeout');
        $this->assertSame('preflop', $state->phase);
        $this->assertSame(2, $state->round);
        $this->assertSame([], array_values(array_filter($state->log, fn ($e) => $e['action'] === 'fold')));
    }

    public function test_players_see_their_own_made_hand_name_once_there_is_a_board(): void
    {
        $state = $this->deal([1000, 1000], 'Ah Kh As Kd Ac 7d 9s Jc 3h');

        $this->assertArrayNotHasKey('hand_name', $this->engine->publicState($state, 1)['players'][0]);

        $state = $this->act($state, 1, 'call');
        $state = $this->act($state, 2, 'check');

        $own = $this->engine->publicState($state, 1)['players'][0];
        $this->assertSame('three_of_a_kind', $own['hand_name']); // A A + A on the flop
        $this->assertArrayNotHasKey('hand_name', $this->engine->publicState($state, 1)['players'][1]);
    }

    public function test_states_saved_before_these_features_still_load(): void
    {
        $state = $this->deal([1000, 1000], '2c 3c 4c 5c 6d 7d 8h 9h Ts');
        $legacy = $state->toArray();
        foreach (['runout_from', 'log', 'turn_deadline', 'turn_seconds', 'hand_ended_at', 'next_hand_at'] as $key) {
            unset($legacy[$key]);
        }
        foreach ($legacy['players'] as &$player) {
            unset($player['last_action'], $player['last_amount']);
        }
        unset($player);

        $restored = PokerState::fromArray($legacy);

        $this->assertNull($restored->turnDeadline);
        $this->assertSame([], $restored->log);
        $this->assertNull($restored->players[0]->lastAction);
        // And play carries on normally from it.
        $restored = $this->engine->applyAction($restored, 1, 'call');
        $this->assertSame(2, $restored->players[1]->userId);
    }
}
