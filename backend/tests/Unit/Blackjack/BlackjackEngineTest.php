<?php

namespace Tests\Unit\Blackjack;

use App\Game\Blackjack\BlackjackActionException;
use App\Game\Blackjack\BlackjackEngine;
use App\Game\Blackjack\BlackjackHand;
use App\Game\Blackjack\BlackjackPlayer;
use App\Game\Blackjack\BlackjackState;
use App\Game\Blackjack\Card;
use App\Game\Blackjack\Deck;
use PHPUnit\Framework\TestCase;

class BlackjackEngineTest extends TestCase
{
    private BlackjackEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new BlackjackEngine;
    }

    /**
     * @param  string[]  $ranks  Cards in the exact order they will be drawn.
     */
    private function deckOf(array $ranks, string $suit = 'spades'): Deck
    {
        return Deck::fromCards(array_map(fn (string $rank) => new Card($rank, $suit), $ranks));
    }

    /**
     * @param  array<int, array{user_id:int, seat:int, chips:int}>  $players
     */
    private function deal(array $players, array $ranks, int $bet = 100, int $round = 1): BlackjackState
    {
        return $this->engine->deal($players, $bet, $round, $this->deckOf($ranks));
    }

    private function twoPlayers(int $chips1 = 1000, int $chips2 = 1000): array
    {
        return [
            ['user_id' => 1, 'seat' => 0, 'chips' => $chips1],
            ['user_id' => 2, 'seat' => 1, 'chips' => $chips2],
        ];
    }

    private function onePlayer(int $chips = 1000): array
    {
        return [['user_id' => 1, 'seat' => 0, 'chips' => $chips]];
    }

    // --- Initial deal & turn order ---------------------------------------

    public function test_deal_sets_correct_turn_order_and_deducts_bets(): void
    {
        // order: P1c1, P2c1, D1, P1c2, P2c2, D2
        $state = $this->deal($this->twoPlayers(), ['5', '7', '2', '6', '8', '3']);

        $this->assertSame('player_turn', $state->phase);
        $this->assertSame(0, $state->currentPlayerIndex);
        $this->assertSame(0, $state->currentHandIndex);
        $this->assertSame(900, $state->players[0]->chips);
        $this->assertSame(900, $state->players[1]->chips);
        $this->assertCount(2, $state->players[0]->hands[0]->cards);
        $this->assertSame(11, $state->players[0]->hands[0]->score()['value']);
        $this->assertSame(15, $state->players[1]->hands[0]->score()['value']);
    }

    public function test_natural_blackjack_is_detected_and_turn_skips_to_next_player(): void
    {
        $state = $this->deal($this->twoPlayers(), ['A', '5', '2', 'K', '6', '3']);

        $this->assertSame('blackjack', $state->players[0]->hands[0]->status);
        $this->assertSame(1, $state->currentPlayerIndex);
    }

    public function test_cannot_act_out_of_turn(): void
    {
        $state = $this->deal($this->twoPlayers(), ['5', '7', '2', '6', '8', '3']);

        $this->expectException(BlackjackActionException::class);
        $this->expectExceptionMessage('It is not your turn.');

        $this->engine->applyAction($state, 2, 'stand');
    }

    public function test_dealer_hole_card_is_hidden_in_public_state(): void
    {
        $state = $this->deal($this->onePlayer(), ['5', '7', '6', 'K']);

        $public = $this->engine->publicState($state, 1);

        $this->assertSame(['rank' => '7', 'suit' => 'spades'], $public['dealer']['cards'][0]);
        $this->assertSame(['hidden' => true], $public['dealer']['cards'][1]);
        $this->assertNull($public['dealer']['score']);
    }

    public function test_allowed_actions_are_empty_for_a_non_current_viewer(): void
    {
        $state = $this->deal($this->twoPlayers(), ['5', '7', '2', '6', '8', '3']);

        $this->assertSame([], $this->engine->getAllowedActions($state, 2));
        $this->assertNotEmpty($this->engine->getAllowedActions($state, 1));
    }

    // --- Hit ---------------------------------------------------------------

    public function test_hit_adds_card_and_keeps_hand_playing(): void
    {
        $state = $this->deal($this->twoPlayers(), ['2', '7', '2', '3', '8', '3', '4']);

        $state = $this->engine->applyAction($state, 1, 'hit');

        $hand = $state->players[0]->hands[0];
        $this->assertSame('playing', $hand->status);
        $this->assertSame(9, $hand->score()['value']);
        $this->assertSame(0, $state->currentPlayerIndex);
        $this->assertSame(0, $state->currentHandIndex);
    }

    public function test_hit_to_exactly_21_auto_stands_and_advances_turn(): void
    {
        // P1 deals to [5,6]=11, hits 'K' -> exactly 21
        $state = $this->deal($this->twoPlayers(), ['5', '7', '2', '6', '8', '3', 'K']);

        $state = $this->engine->applyAction($state, 1, 'hit');

        $this->assertSame('stood', $state->players[0]->hands[0]->status);
        $this->assertSame(21, $state->players[0]->hands[0]->score()['value']);
        $this->assertSame(1, $state->currentPlayerIndex);
    }

    public function test_hit_to_bust_settles_as_a_loss(): void
    {
        // deal: [10,2,9,3] -> P1=19, dealer=[2,3]; hit '5' -> 24 bust; dealer draws '9','5' to reach 19
        $state = $this->deal($this->onePlayer(), ['10', '2', '9', '3', '5', '9', '5']);

        $state = $this->engine->applyAction($state, 1, 'hit');

        $hand = $state->players[0]->hands[0];
        $this->assertSame('bust', $hand->status);
        $this->assertSame('round_finished', $state->phase);
        $this->assertSame('lose', $hand->result);
        $this->assertSame(900, $state->players[0]->chips);
    }

    // --- Stand / turn advancement / dealer play ----------------------------

    public function test_stand_advances_to_next_player_then_dealer_plays_and_settles(): void
    {
        // deal: P1=[10,8]=18, P2=[10,9]=19, dealer=[7,6]=13 hidden; dealer hits '5' -> 18
        $state = $this->deal($this->twoPlayers(), ['10', '10', '7', '8', '9', '6', '5']);

        $state = $this->engine->applyAction($state, 1, 'stand');
        $this->assertSame(1, $state->currentPlayerIndex);
        $this->assertSame('player_turn', $state->phase);

        $state = $this->engine->applyAction($state, 2, 'stand');

        $this->assertSame('round_finished', $state->phase);
        $this->assertFalse($state->dealerHoleHidden);
        $this->assertSame(18, (new BlackjackHand($state->dealerCards, 0))->score()['value']);

        $this->assertSame('push', $state->players[0]->hands[0]->result);
        $this->assertSame(1000, $state->players[0]->chips);

        $this->assertSame('win', $state->players[1]->hands[0]->result);
        $this->assertSame(1100, $state->players[1]->chips);
    }

    public function test_dealer_busts_all_remaining_players_win(): void
    {
        // deal: P1=[10,7]=17, dealer=[10,6]=16 hidden; dealer hits '10' -> 26 bust
        $state = $this->deal($this->onePlayer(), ['10', '10', '7', '6', '10']);

        $state = $this->engine->applyAction($state, 1, 'stand');

        $this->assertSame('round_finished', $state->phase);
        $this->assertSame('win', $state->players[0]->hands[0]->result);
        $this->assertSame(1100, $state->players[0]->chips);
    }

    public function test_dealer_stands_on_soft_17(): void
    {
        // dealer = [A,6] = soft 17, must NOT hit; player stands with 20
        $state = $this->deal($this->onePlayer(), ['10', 'A', '10', '6']);

        $state = $this->engine->applyAction($state, 1, 'stand');

        $this->assertCount(2, $state->dealerCards, 'dealer must not draw on soft 17');
        $this->assertSame('round_finished', $state->phase);
        $this->assertSame('win', $state->players[0]->hands[0]->result);
    }

    public function test_dealer_hits_below_17(): void
    {
        // dealer = [7,6] = 13, must hit until >= 17 (draws '2' -> 15, then '9' -> 24)
        $state = $this->deal($this->onePlayer(), ['10', '7', '9', '6', '2', '9']);

        $state = $this->engine->applyAction($state, 1, 'stand');

        $this->assertGreaterThan(2, count($state->dealerCards));
    }

    // --- Blackjack settlement ------------------------------------------------

    public function test_player_blackjack_pays_three_to_two(): void
    {
        $state = $this->deal($this->onePlayer(), ['A', '2', 'K', '3', '9', '5']);

        $hand = $state->players[0]->hands[0];
        $this->assertSame('blackjack', $hand->status);
        $this->assertSame('round_finished', $state->phase, 'a solo natural blackjack ends the round immediately');
        $this->assertSame('blackjack', $hand->result);
        $this->assertSame(1150, $state->players[0]->chips);
    }

    public function test_dealer_blackjack_beats_ordinary_hand_but_pushes_player_blackjack(): void
    {
        // P1 = [A,K] natural, P2 = [10,10] ordinary 20, dealer = [A,10] natural.
        // Dealer Blackjack is detected right after the deal, so no player
        // ever gets to act — the round settles immediately.
        $state = $this->deal($this->twoPlayers(), ['A', '10', 'A', 'K', '10', '10']);

        $this->assertSame('round_finished', $state->phase);
        $this->assertNull($state->currentPlayerIndex);

        $this->assertSame('push', $state->players[0]->hands[0]->result);
        $this->assertSame(1000, $state->players[0]->chips);

        $this->assertSame('lose', $state->players[1]->hands[0]->result);
        $this->assertSame(900, $state->players[1]->chips);
    }

    public function test_settlement_three_card_twenty_one_loses_to_dealer_blackjack(): void
    {
        // A 3-card 21 (7+4+K) vs a dealer natural can never actually arise
        // through normal play any more — dealer Blackjack is now settled
        // immediately at deal time, before any player gets to act. Exercise
        // the settlement rule itself directly instead (BlackjackEngine::settle
        // is private and pure, so reflection is the honest way to reach it).
        $player = new BlackjackPlayer(1, 0, 900);
        $hand = new BlackjackHand(
            [new Card('7', 'spades'), new Card('4', 'hearts'), new Card('K', 'clubs')],
            100,
            'stood',
        );
        $player->hands = [$hand];

        $state = new BlackjackState(
            phase: 'dealer_turn',
            deck: Deck::fromCards([]),
            dealerCards: [new Card('A', 'spades'), new Card('K', 'hearts')],
            dealerHoleHidden: false,
            players: [$player],
            currentPlayerIndex: null,
            currentHandIndex: null,
            round: 1,
        );

        (new \ReflectionMethod(BlackjackEngine::class, 'settle'))->invoke($this->engine, $state);

        $this->assertSame(21, $hand->score()['value']);
        $this->assertSame('round_finished', $state->phase);
        $this->assertSame('lose', $hand->result);
        $this->assertSame(900, $player->chips, 'no payout on a loss');
    }

    public function test_dealer_natural_king_ace_order_is_still_blackjack(): void
    {
        // dealer's up-card is a ten-value card, hole card is the Ace
        $state = $this->deal($this->onePlayer(), ['9', 'K', '8', 'A']);

        $this->assertSame('round_finished', $state->phase, 'dealer K+A must be detected as Blackjack');
        $this->assertSame('lose', $state->players[0]->hands[0]->result);
    }

    // --- Double --------------------------------------------------------------

    public function test_double_doubles_bet_draws_one_card_and_finishes_hand(): void
    {
        // deal [5,2,6,3] -> P1=11, dealer=[2,3]; double draws '5' -> 16; dealer hits '9','6' -> 20
        $state = $this->deal($this->onePlayer(), ['5', '2', '6', '3', '5', '9', '6']);

        $state = $this->engine->applyAction($state, 1, 'double');

        $hand = $state->players[0]->hands[0];
        $this->assertSame(200, $hand->bet);
        $this->assertCount(3, $hand->cards);
        $this->assertSame('finished', $hand->status);
        $this->assertSame('round_finished', $state->phase);
        $this->assertSame('lose', $hand->result);
        $this->assertSame(800, $state->players[0]->chips);
    }

    public function test_cannot_double_without_enough_chips(): void
    {
        $state = $this->deal([['user_id' => 1, 'seat' => 0, 'chips' => 100]], ['5', '2', '6', '3']);

        $this->expectException(BlackjackActionException::class);
        $this->expectExceptionMessage('Double is not allowed.');

        $this->engine->applyAction($state, 1, 'double');
    }

    public function test_cannot_double_after_hit(): void
    {
        $state = $this->deal($this->onePlayer(), ['5', '2', '6', '3', '4']);
        $state = $this->engine->applyAction($state, 1, 'hit');

        $this->expectException(BlackjackActionException::class);
        $this->expectExceptionMessage('Double is not allowed.');

        $this->engine->applyAction($state, 1, 'double');
    }

    // --- Split ---------------------------------------------------------------

    public function test_pair_can_split_into_two_hands_with_a_new_card_each(): void
    {
        $state = $this->deal($this->onePlayer(), ['8', '2', '8', '3', '3', 'K']);

        $state = $this->engine->applyAction($state, 1, 'split');

        $player = $state->players[0];
        $this->assertCount(2, $player->hands);
        $this->assertTrue($player->hands[0]->isSplit);
        $this->assertTrue($player->hands[1]->isSplit);
        $this->assertCount(2, $player->hands[0]->cards);
        $this->assertCount(2, $player->hands[1]->cards);
        $this->assertSame(100, $player->hands[0]->bet);
        $this->assertSame(100, $player->hands[1]->bet);
        $this->assertSame(800, $player->chips);
        $this->assertSame(0, $state->currentPlayerIndex);
        $this->assertSame(0, $state->currentHandIndex);
    }

    public function test_non_pair_cannot_split(): void
    {
        $state = $this->deal($this->onePlayer(), ['8', '2', '9', '3']);

        $this->expectException(BlackjackActionException::class);
        $this->expectExceptionMessage('Split is not allowed.');

        $this->engine->applyAction($state, 1, 'split');
    }

    public function test_cannot_split_without_enough_chips(): void
    {
        $state = $this->deal([['user_id' => 1, 'seat' => 0, 'chips' => 100]], ['8', '2', '8', '3']);

        $this->expectException(BlackjackActionException::class);
        $this->expectExceptionMessage('Split is not allowed.');

        $this->engine->applyAction($state, 1, 'split');
    }

    public function test_cannot_split_more_than_once(): void
    {
        // splitting [8,8] where the new card for hand A is also '8' (another pair)
        $state = $this->deal($this->onePlayer(), ['8', '2', '8', '3', '8', 'K']);
        $state = $this->engine->applyAction($state, 1, 'split');

        $this->assertSame(['8', '8'], array_map(fn ($c) => $c->rank, $state->players[0]->hands[0]->cards));

        $this->expectException(BlackjackActionException::class);
        $this->expectExceptionMessage('Split is not allowed.');

        $this->engine->applyAction($state, 1, 'split');
    }

    public function test_second_split_hand_is_played_after_the_first(): void
    {
        // split [8,8] -> handA +'3'=11, handB +'K'=18; then dealer draws '9','5' -> 19
        $state = $this->deal($this->onePlayer(), ['8', '2', '8', '3', '3', 'K', '9', '5']);
        $state = $this->engine->applyAction($state, 1, 'split');

        $state = $this->engine->applyAction($state, 1, 'stand');
        $this->assertSame(1, $state->currentHandIndex, 'turn should move to the second split hand');
        $this->assertSame('player_turn', $state->phase);

        $state = $this->engine->applyAction($state, 1, 'stand');

        $this->assertSame('round_finished', $state->phase);
        $this->assertNotNull($state->players[0]->hands[0]->result);
        $this->assertNotNull($state->players[0]->hands[1]->result);
    }

    public function test_split_aces_each_get_one_card_and_automatically_stand(): void
    {
        // split [A,A] -> handA +'9'=20, handB +'6'=17; dealer [2,3] hits '9','5' -> 19
        $state = $this->deal($this->onePlayer(), ['A', '2', 'A', '3', '9', '6', '9', '5']);

        $state = $this->engine->applyAction($state, 1, 'split');

        $player = $state->players[0];
        $this->assertSame('stood', $player->hands[0]->status);
        $this->assertSame('stood', $player->hands[1]->status);
        $this->assertSame('round_finished', $state->phase, 'split aces auto-finish the whole round');
        $this->assertSame('win', $player->hands[0]->result);
        $this->assertSame('lose', $player->hands[1]->result);
        $this->assertSame(1000, $player->chips);
    }

    // --- Blackjack after Split (this project's rule: Ace + ten-value,
    // exactly 2 cards, regardless of split origin — see BlackjackHand::isBlackjack()) --

    public function test_split_ace_pair_receiving_ten_value_card_is_blackjack(): void
    {
        // split [A,A] -> handA +'K' = Blackjack (auto-finishes), handB +'5' = 16 (auto-stands, aces rule)
        $state = $this->deal($this->onePlayer(), ['A', '2', 'A', '3', 'K', '5', '9', '5']);

        $state = $this->engine->applyAction($state, 1, 'split');

        $player = $state->players[0];
        $this->assertSame('blackjack', $player->hands[0]->status);
        $this->assertSame('stood', $player->hands[1]->status);
        $this->assertSame('round_finished', $state->phase);
        $this->assertSame('blackjack', $player->hands[0]->result);
        $this->assertSame(1050, $player->chips, '800 after both bets, +250 blackjack payout, +0 lose');
    }

    public function test_split_ace_pair_both_hands_receiving_ten_value_cards_are_both_blackjack(): void
    {
        // split [A,A] -> handA +'K' = Blackjack, handB +'Q' = Blackjack
        $state = $this->deal($this->onePlayer(), ['A', '2', 'A', '3', 'K', 'Q', '9', '5']);

        $state = $this->engine->applyAction($state, 1, 'split');

        $player = $state->players[0];
        $this->assertSame('blackjack', $player->hands[0]->status);
        $this->assertSame('blackjack', $player->hands[1]->status);
        $this->assertSame('round_finished', $state->phase);
        $this->assertSame('blackjack', $player->hands[0]->result);
        $this->assertSame('blackjack', $player->hands[1]->result);
        $this->assertSame(1300, $player->chips, '800 after both bets, +250 +250 blackjack payouts');
    }

    public function test_split_ten_pair_receiving_ace_is_blackjack(): void
    {
        // split [10,10] -> handA +'A' = Blackjack (auto-finishes and turn moves on),
        // handB +'5' = 15, still playing (a non-Ace pair split is never auto-stood)
        $state = $this->deal($this->onePlayer(), ['10', '2', '10', '3', 'A', '5']);

        $state = $this->engine->applyAction($state, 1, 'split');

        $player = $state->players[0];
        $this->assertSame('blackjack', $player->hands[0]->status);
        $this->assertSame('playing', $player->hands[1]->status);
        $this->assertSame('player_turn', $state->phase, 'handB still needs to act');
        $this->assertSame(1, $state->currentHandIndex, 'turn moved past the resolved handA');
    }

    public function test_split_ten_pair_both_hands_receiving_aces_are_both_blackjack(): void
    {
        $state = $this->deal($this->onePlayer(), ['10', '2', '10', '3', 'A', 'A', '9', '5']);

        $state = $this->engine->applyAction($state, 1, 'split');

        $player = $state->players[0];
        $this->assertSame('blackjack', $player->hands[0]->status);
        $this->assertSame('blackjack', $player->hands[1]->status);
        $this->assertSame('round_finished', $state->phase);
        $this->assertSame(1300, $player->chips);
    }

    public function test_settlement_dealer_blackjack_vs_split_blackjack_is_push(): void
    {
        // Dealer Blackjack now settles immediately at deal time, so it can
        // never coincide with a Split having already happened in real play.
        // Test the settlement rule itself directly (see the 3-card-21 test
        // above for the same reasoning).
        $player = new BlackjackPlayer(1, 0, 800);
        $handA = new BlackjackHand([new Card('A', 'spades'), new Card('Q', 'hearts')], 100, 'blackjack', null, true);
        $handB = new BlackjackHand([new Card('A', 'clubs'), new Card('8', 'diamonds')], 100, 'stood', null, true);
        $player->hands = [$handA, $handB];

        $state = new BlackjackState(
            phase: 'dealer_turn',
            deck: Deck::fromCards([]),
            dealerCards: [new Card('A', 'hearts'), new Card('K', 'spades')],
            dealerHoleHidden: false,
            players: [$player],
            currentPlayerIndex: null,
            currentHandIndex: null,
            round: 1,
        );

        (new \ReflectionMethod(BlackjackEngine::class, 'settle'))->invoke($this->engine, $state);

        $this->assertSame('push', $handA->result);
        $this->assertSame('lose', $handB->result);
        $this->assertSame(900, $player->chips, '800 + 100 push on handA, +0 on handB');
    }

    // --- Round / out players ---------------------------------------------

    public function test_deal_marks_player_with_insufficient_chips_as_out(): void
    {
        $state = $this->deal($this->twoPlayers(1000, 50), ['5', '7', '2', '6', '8', '3']);

        $this->assertSame('out', $state->players[1]->status);
        $this->assertSame([], $state->players[1]->hands);
        $this->assertSame(0, $state->currentPlayerIndex, 'only the active player takes a turn');
    }

    public function test_next_round_increments_round_number_and_deals_fresh_hands(): void
    {
        $state = $this->deal($this->onePlayer(), ['10', '2', '9', '3', '5', '9', '5']);
        $state = $this->engine->applyAction($state, 1, 'hit'); // busts, round finishes

        $this->assertSame('round_finished', $state->phase);
        $this->assertSame(1, $state->round);

        $freshDeck = $this->deckOf(['5', '2', '6', '3']);
        $state = $this->engine->nextRound($state, 100, $freshDeck);

        $this->assertSame(2, $state->round);
        $this->assertSame('player_turn', $state->phase);
        $this->assertCount(2, $state->players[0]->hands[0]->cards);
    }

    public function test_next_round_skips_players_without_enough_chips(): void
    {
        $state = $this->deal($this->twoPlayers(1000, 150), ['5', '5', '10', '10', '10', '10']);
        $state = $this->engine->applyAction($state, 1, 'stand');
        $state = $this->engine->applyAction($state, 2, 'stand');

        $this->assertSame('round_finished', $state->phase);
        $this->assertSame(50, $state->players[1]->chips);

        $freshDeck = Deck::standard()->shuffle();
        $state = $this->engine->nextRound($state, 100, $freshDeck);

        $this->assertSame(2, $state->round);
        $this->assertSame('out', $state->players[1]->status);
        $this->assertSame([], $state->players[1]->hands);
    }

    public function test_next_round_throws_if_current_round_not_finished(): void
    {
        $state = $this->deal($this->twoPlayers(), ['5', '7', '2', '6', '8', '3']);

        $this->expectException(BlackjackActionException::class);

        $this->engine->nextRound($state, 100, Deck::standard()->shuffle());
    }
}
