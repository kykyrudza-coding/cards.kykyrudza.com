<?php

namespace Tests\Unit\Durak;

use App\Game\Durak\Card;
use App\Game\Durak\Deck;
use PHPUnit\Framework\TestCase;

class DeckTest extends TestCase
{
    public function test_standard_deck_has_36_unique_cards(): void
    {
        $deck = Deck::standard();

        $this->assertSame(36, $deck->remaining());

        $seen = [];
        while ($deck->remaining() > 0) {
            $card = $deck->draw();
            $key = $card->rank.'|'.$card->suit;
            $this->assertArrayNotHasKey($key, $seen);
            $seen[$key] = true;
        }
        $this->assertCount(36, $seen);
    }

    public function test_standard_deck_excludes_ranks_below_six(): void
    {
        $deck = Deck::standard();
        $ranks = [];
        while ($deck->remaining() > 0) {
            $ranks[] = $deck->draw()->rank;
        }

        foreach (['2', '3', '4', '5'] as $lowRank) {
            $this->assertNotContains($lowRank, $ranks);
        }
    }

    public function test_draw_from_empty_deck_throws(): void
    {
        $deck = Deck::fromCards([]);
        $this->expectException(\RuntimeException::class);
        $deck->draw();
    }

    public function test_put_on_bottom_is_drawn_last(): void
    {
        $deck = Deck::fromCards([new Card('6', 'clubs'), new Card('7', 'clubs')]);
        $deck->putOnBottom(new Card('A', 'spades'));

        $this->assertSame('6', $deck->draw()->rank);
        $this->assertSame('7', $deck->draw()->rank);
        $this->assertSame('A', $deck->draw()->rank);
    }
}
