<?php

namespace Tests\Unit\Blackjack;

use App\Game\Blackjack\Card;
use App\Game\Blackjack\Deck;
use PHPUnit\Framework\TestCase;

class DeckTest extends TestCase
{
    public function test_standard_deck_has_52_unique_cards(): void
    {
        $deck = Deck::standard();

        $this->assertSame(52, $deck->remaining());

        $unique = [];
        while ($deck->remaining() > 0) {
            $card = $deck->draw();
            $unique[$card->rank.'-'.$card->suit] = true;
        }

        $this->assertCount(52, $unique);
    }

    public function test_draw_removes_card_from_deck(): void
    {
        $deck = Deck::standard();
        $before = $deck->remaining();

        $deck->draw();

        $this->assertSame($before - 1, $deck->remaining());
    }

    public function test_draw_from_empty_deck_throws(): void
    {
        $deck = Deck::fromCards([]);

        $this->expectException(\RuntimeException::class);

        $deck->draw();
    }

    public function test_shuffle_keeps_the_same_cards(): void
    {
        $deck = Deck::standard()->shuffle();

        $this->assertSame(52, $deck->remaining());
    }

    public function test_from_cards_is_deterministic(): void
    {
        $cards = [new Card('A', 'spades'), new Card('K', 'hearts')];
        $deck = Deck::fromCards($cards);

        $this->assertSame(2, $deck->remaining());
        $first = $deck->draw();
        $this->assertSame('A', $first->rank);
        $this->assertSame('spades', $first->suit);
    }

    public function test_round_trips_through_array(): void
    {
        $deck = Deck::fromCards([new Card('10', 'clubs'), new Card('Q', 'diamonds')]);
        $restored = Deck::fromArrayData($deck->toArray());

        $this->assertSame($deck->remaining(), $restored->remaining());
        $this->assertSame('10', $restored->draw()->rank);
    }
}
