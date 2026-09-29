<?php

namespace Tests\Unit\Blackjack;

use App\Game\Blackjack\BlackjackHand;
use App\Game\Blackjack\Card;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlackjackHandTest extends TestCase
{
    public function test_ace_plus_nine_scores_twenty(): void
    {
        $hand = new BlackjackHand([new Card('A', 'spades'), new Card('9', 'hearts')], 100);

        $this->assertSame(20, $hand->score()['value']);
    }

    public function test_ace_plus_nine_plus_ace_scores_twenty_one(): void
    {
        $hand = new BlackjackHand([new Card('A', 'spades'), new Card('9', 'hearts'), new Card('A', 'clubs')], 100);

        $this->assertSame(21, $hand->score()['value']);
    }

    public function test_ace_plus_ace_plus_nine_scores_twenty_one(): void
    {
        $hand = new BlackjackHand([new Card('A', 'spades'), new Card('A', 'clubs'), new Card('9', 'hearts')], 100);

        $this->assertSame(21, $hand->score()['value']);
    }

    public function test_king_plus_queen_scores_twenty(): void
    {
        $hand = new BlackjackHand([new Card('K', 'spades'), new Card('Q', 'hearts')], 100);

        $this->assertSame(20, $hand->score()['value']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function blackjackCombinations(): array
    {
        return [
            'A+K' => ['A', 'K'],
            'K+A' => ['K', 'A'],
            'A+Q' => ['A', 'Q'],
            'Q+A' => ['Q', 'A'],
            'A+J' => ['A', 'J'],
            'J+A' => ['J', 'A'],
            'A+10' => ['A', '10'],
            '10+A' => ['10', 'A'],
        ];
    }

    #[DataProvider('blackjackCombinations')]
    public function test_ace_plus_ten_value_card_is_blackjack_regardless_of_order(string $rank1, string $rank2): void
    {
        $hand = new BlackjackHand([new Card($rank1, 'spades'), new Card($rank2, 'hearts')], 100);

        $this->assertSame(21, $hand->score()['value']);
        $this->assertTrue($hand->isBlackjack());
    }

    public function test_split_hand_ace_plus_ten_value_is_still_blackjack(): void
    {
        // This project's rule: Blackjack depends only on the two cards in
        // the hand, never on how the hand was created (see BlackjackHand::isBlackjack()).
        $hand = new BlackjackHand([new Card('A', 'spades'), new Card('K', 'hearts')], 100, isSplit: true);

        $this->assertSame(21, $hand->score()['value']);
        $this->assertTrue($hand->isBlackjack());
    }

    public function test_three_card_twenty_one_is_never_blackjack(): void
    {
        $hand = new BlackjackHand([new Card('7', 'spades'), new Card('4', 'hearts'), new Card('K', 'clubs')], 100);

        $this->assertSame(21, $hand->score()['value']);
        $this->assertFalse($hand->isBlackjack());
    }

    public function test_ace_plus_five_plus_five_is_never_blackjack(): void
    {
        $hand = new BlackjackHand([new Card('A', 'spades'), new Card('5', 'hearts'), new Card('5', 'clubs')], 100);

        $this->assertSame(21, $hand->score()['value']);
        $this->assertFalse($hand->isBlackjack());
    }

    public function test_ten_plus_five_plus_six_is_never_blackjack(): void
    {
        $hand = new BlackjackHand([new Card('10', 'spades'), new Card('5', 'hearts'), new Card('6', 'clubs')], 100);

        $this->assertSame(21, $hand->score()['value']);
        $this->assertFalse($hand->isBlackjack());
    }

    public function test_two_tens_are_not_blackjack(): void
    {
        $hand = new BlackjackHand([new Card('10', 'spades'), new Card('10', 'hearts')], 100);

        $this->assertSame(20, $hand->score()['value']);
        $this->assertFalse($hand->isBlackjack());
    }

    public function test_two_aces_are_not_blackjack(): void
    {
        $hand = new BlackjackHand([new Card('A', 'spades'), new Card('A', 'hearts')], 100);

        $this->assertFalse($hand->isBlackjack());
    }

    public function test_bust_detection(): void
    {
        $hand = new BlackjackHand([new Card('K', 'spades'), new Card('Q', 'hearts'), new Card('5', 'clubs')], 100);

        $this->assertTrue($hand->isBust());
        $this->assertSame(25, $hand->score()['value']);
    }

    public function test_soft_hand_is_flagged(): void
    {
        $hand = new BlackjackHand([new Card('A', 'spades'), new Card('6', 'hearts')], 100);

        $score = $hand->score();
        $this->assertSame(17, $score['value']);
        $this->assertTrue($score['soft']);
    }

    public function test_round_trips_through_array(): void
    {
        $hand = new BlackjackHand([new Card('A', 'spades'), new Card('K', 'hearts')], 100);
        $restored = BlackjackHand::fromArray($hand->toArray());

        $this->assertSame($hand->score()['value'], $restored->score()['value']);
        $this->assertSame($hand->bet, $restored->bet);
        $this->assertSame($hand->status, $restored->status);
    }
}
