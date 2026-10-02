<?php

namespace Tests\Unit\Poker;

use App\Game\Poker\Card;
use App\Game\Poker\Deck;
use App\Game\Poker\PokerActionException;
use App\Game\Poker\PokerEngine;
use App\Game\Poker\PokerState;
use PHPUnit\Framework\TestCase;

class PokerEngineTest extends TestCase
{
    private PokerEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new PokerEngine;
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
     * @param  int[]  $chips  Stack per user, user ids 1..n at seats 0..n-1.
     * @param  string  $deck  Hole cards are dealt starting at the small blind (two passes), then flop, turn, river.
     */
    private function deal(array $chips, string $deck, int $bigBlind = 100): PokerState
    {
        $players = [];
        foreach ($chips as $i => $stack) {
            $players[] = ['user_id' => $i + 1, 'seat' => $i, 'chips' => $stack];
        }

        // Filler so tests that run past the scripted cards still have a deck to draw from.
        $filler = $this->cards('2d 3d 4d 5d 6d 7d 8d 9d 10d Jd Qd Kd Ad 2h 3h 4h 5h 6h 7h 8h');

        return $this->engine->deal($players, $bigBlind, Deck::fromCards([...$this->cards($deck), ...$filler]));
    }

    private function chipsOf(PokerState $state, int $userId): int
    {
        foreach ($state->players as $player) {
            if ($player->userId === $userId) {
                return $player->chips;
            }
        }
        $this->fail('Unknown player');
    }

    private function act(PokerState $state, int $userId, string $action, array $payload = []): PokerState
    {
        return $this->engine->applyAction($state, $userId, $action, $payload);
    }

    public function test_deal_posts_blinds_and_gives_the_first_turn_after_the_big_blind(): void
    {
        // 3 players: dealer = user 1 (seat 0), SB = user 2, BB = user 3, first to act = user 1.
        $state = $this->deal([1000, 1000, 1000], '2c 3c 4c 5c 6c 7c');

        $this->assertSame('preflop', $state->phase);
        $this->assertSame(1000 - 50, $this->chipsOf($state, 2));
        $this->assertSame(1000 - 100, $this->chipsOf($state, 3));
        $this->assertSame(100, $state->currentBet);
        $this->assertSame(1, $state->players[$state->currentIndex]->userId);
        $this->assertCount(2, $state->players[0]->hand);
    }

    public function test_heads_up_dealer_is_small_blind_and_acts_first_preflop_but_last_after(): void
    {
        $state = $this->deal([1000, 1000], '2c 3c 4c 5c 6d 7d 8h 9h Ts');

        $this->assertSame(950, $this->chipsOf($state, 1)); // dealer = SB
        $this->assertSame(900, $this->chipsOf($state, 2));
        $this->assertSame(1, $state->players[$state->currentIndex]->userId);

        $state = $this->act($state, 1, 'call');
        $state = $this->act($state, 2, 'check');

        $this->assertSame('flop', $state->phase);
        $this->assertCount(3, $state->community);
        $this->assertSame(2, $state->players[$state->currentIndex]->userId); // BB acts first postflop
    }

    public function test_fold_ends_the_hand_and_awards_the_pot_without_a_showdown(): void
    {
        $state = $this->deal([1000, 1000], '2c 3c 4c 5c 6d 7d 8h 9h Ts');

        $state = $this->act($state, 1, 'fold');

        $this->assertSame('hand_finished', $state->phase);
        $this->assertFalse($state->showdown);
        $this->assertSame(1050, $this->chipsOf($state, 2));
        $this->assertSame(950, $this->chipsOf($state, 1));
    }

    public function test_only_the_player_to_act_can_act_and_checking_into_a_bet_is_rejected(): void
    {
        $state = $this->deal([1000, 1000, 1000], '2c 3c 4c 5c 6c 7c');

        try {
            $this->act($state, 2, 'call');
            $this->fail('Expected a turn violation.');
        } catch (PokerActionException $e) {
            $this->assertSame(403, $e->status);
        }

        $this->expectException(PokerActionException::class);
        $this->act($state, 1, 'check');
    }

    public function test_raise_must_meet_the_minimum_and_reopens_the_betting(): void
    {
        $state = $this->deal([1000, 1000, 1000], '2c 3c 4c 5c 6c 7c');

        try {
            $this->act($state, 1, 'raise', ['amount' => 150]); // min raise-to is 200
            $this->fail('Expected a minimum-raise violation.');
        } catch (PokerActionException) {
            // expected
        }

        $state = $this->act($state, 1, 'raise', ['amount' => 300]);
        $state = $this->act($state, 2, 'call');
        $state = $this->act($state, 3, 'call');

        $this->assertSame('flop', $state->phase);
        $this->assertSame(700, $this->chipsOf($state, 1));
        $this->assertSame(0, $state->currentBet);
        $this->assertSame(100, $state->minRaise); // resets to the big blind on a new street
    }

    public function test_big_blind_gets_an_option_after_limpers(): void
    {
        $state = $this->deal([1000, 1000, 1000], '2c 3c 4c 5c 6c 7c');

        $state = $this->act($state, 1, 'call');
        $state = $this->act($state, 2, 'call');

        $this->assertSame('preflop', $state->phase);
        $this->assertSame(3, $state->players[$state->currentIndex]->userId);

        $state = $this->act($state, 3, 'check');
        $this->assertSame('flop', $state->phase);
    }

    public function test_showdown_pays_the_best_hand_and_reveals_hole_cards(): void
    {
        // Heads-up: user 1 (SB) gets 2c,4c... deal order: SB, BB, SB, BB.
        // user 1: Ah Ad; user 2: Kh Kd; board: 2c 7d 9s Jc 3h.
        $state = $this->deal([1000, 1000], 'Ah Kh Ad Kd 2c 7d 9s Jc 3h');

        $state = $this->act($state, 1, 'call');
        $state = $this->act($state, 2, 'check');
        foreach ([2, 1, 2, 1, 2, 1] as $userId) {
            $state = $this->act($state, $userId, 'check');
        }

        $this->assertSame('hand_finished', $state->phase);
        $this->assertTrue($state->showdown);
        $this->assertSame(1100, $this->chipsOf($state, 1));
        $this->assertSame(900, $this->chipsOf($state, 2));
        $this->assertSame('pair', $state->results[0]['hand']);

        $view = $this->engine->publicState($state, 2);
        $this->assertArrayHasKey('hand', $view['players'][0]); // opponent revealed at showdown
    }

    public function test_opponent_hole_cards_stay_hidden_during_a_hand(): void
    {
        $state = $this->deal([1000, 1000], '2c 3c 4c 5c 6d 7d 8h 9h Ts');

        $view = $this->engine->publicState($state, 1);

        $this->assertArrayHasKey('hand', $view['players'][0]);
        $this->assertArrayNotHasKey('hand', $view['players'][1]);
        $this->assertSame(2, $view['players'][1]['hand_count']);
    }

    public function test_exact_tie_splits_the_pot_with_the_odd_chip_left_of_the_dealer(): void
    {
        // Blinds 1/2. Dealer u1 limps, SB u2 folds (1 chip dead), BB u3 checks: pot = 5.
        // u1 and u3 both play the board's broadway straight, so the pot splits 3/2.
        // The odd chip goes to u3 — the next live seat left of the dealer.
        $state = $this->deal([1000, 1000, 1000], '2c 3d 4h 4s 5c 6d As Kd Qh Jc 10s', bigBlind: 2);

        $state = $this->act($state, 1, 'call');
        $state = $this->act($state, 2, 'fold');
        $state = $this->act($state, 3, 'check');
        foreach ([3, 1, 3, 1, 3, 1] as $userId) {
            $state = $this->act($state, $userId, 'check');
        }

        $this->assertSame('hand_finished', $state->phase);
        $this->assertSame(1000, $this->chipsOf($state, 1));
        $this->assertSame(999, $this->chipsOf($state, 2));
        $this->assertSame(1001, $this->chipsOf($state, 3));
    }

    public function test_all_in_with_a_short_stack_creates_a_side_pot(): void
    {
        // 3 players, user 3 is short. Deal order: SB u2, BB u3, dealer u1, then again.
        // u2: As Ad  | u3: Ks Kd | u1: Qs Qd    board: 2c 7d 9h Jc 3s (no help to anyone)
        $state = $this->deal([1000, 1000, 300], 'As Ks Qs Ad Kd Qd 2c 7d 9h Jc 3s');

        $state = $this->act($state, 1, 'raise', ['amount' => 1000]); // dealer shoves
        $state = $this->act($state, 2, 'call'); // SB calls, covered
        $state = $this->act($state, 3, 'call'); // BB calls all-in for 300 total

        // Everyone is all-in (or called): board runs out, results settle.
        $this->assertTrue($state->showdown);
        // u2 holds aces: wins main (900) + side (1400) = 2300.
        $this->assertSame(2300, $this->chipsOf($state, 2));
        $this->assertSame(0, $this->chipsOf($state, 1));
        $this->assertSame(0, $this->chipsOf($state, 3));
        $this->assertSame('finished', $state->phase);
        $this->assertSame(2, $state->winnerId);
    }

    public function test_next_hand_waits_for_everyone_then_rotates_the_button(): void
    {
        $state = $this->deal([1000, 1000, 1000], '2c 3c 4c 5c 6c 7c');
        $state = $this->act($state, 1, 'fold');
        $state = $this->act($state, 2, 'fold');
        $this->assertSame('hand_finished', $state->phase);

        $state = $this->act($state, 1, 'next_hand');
        $this->assertSame('hand_finished', $state->phase);
        $state = $this->act($state, 2, 'next_hand');
        $this->assertSame('hand_finished', $state->phase);

        $state = $this->act($state, 3, 'next_hand');
        $this->assertSame('preflop', $state->phase);
        $this->assertSame(2, $state->round);
        $this->assertSame(1, $state->dealerIndex);
    }

    public function test_match_finishes_when_one_player_holds_every_chip(): void
    {
        // Heads-up all-in: u1 (SB, dealer) has Ah Ad vs u2 Kh Kd.
        $state = $this->deal([500, 500], 'Ah Kh Ad Kd 2c 7d 9s Jc 3h');

        $state = $this->act($state, 1, 'all_in');
        $state = $this->act($state, 2, 'call');

        $this->assertSame('finished', $state->phase);
        $this->assertSame(1, $state->winnerId);
        $this->assertSame(1000, $this->chipsOf($state, 1));
        $this->assertSame(0, $this->chipsOf($state, 2));
        $this->assertSame([], $this->engine->getAllowedActions($state, 1));
    }

    public function test_allowed_actions_for_the_acting_player(): void
    {
        $state = $this->deal([1000, 1000, 1000], '2c 3c 4c 5c 6c 7c');

        $this->assertSame(['fold', 'call', 'raise', 'all_in'], $this->engine->getAllowedActions($state, 1));
        $this->assertSame([], $this->engine->getAllowedActions($state, 2));

        $state = $this->act($state, 1, 'call');
        $state = $this->act($state, 2, 'call');
        $this->assertSame(['fold', 'check', 'raise', 'all_in'], $this->engine->getAllowedActions($state, 3));
    }

    public function test_state_survives_a_serialization_round_trip(): void
    {
        $state = $this->deal([1000, 1000, 1000], '2c 3c 4c 5c 6c 7c');
        $state = $this->act($state, 1, 'raise', ['amount' => 300]);

        $restored = PokerState::fromArray($state->toArray());

        $this->assertEquals($state->toArray(), $restored->toArray());
    }
}
