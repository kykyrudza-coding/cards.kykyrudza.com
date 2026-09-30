<?php

namespace Tests\Unit\Durak;

use App\Game\Durak\Card;
use PHPUnit\Framework\TestCase;

class CardTest extends TestCase
{
    public function test_strength_orders_ranks_low_to_high(): void
    {
        $this->assertLessThan((new Card('7', 'spades'))->strength(), (new Card('6', 'spades'))->strength());
        $this->assertLessThan((new Card('A', 'spades'))->strength(), (new Card('K', 'spades'))->strength());
        $this->assertSame(0, (new Card('6', 'spades'))->strength());
        $this->assertSame(8, (new Card('A', 'spades'))->strength());
    }

    public function test_round_trips_through_array(): void
    {
        $card = new Card('10', 'hearts');

        $this->assertSame(['rank' => '10', 'suit' => 'hearts'], $card->toArray());
        $restored = Card::fromArray($card->toArray());
        $this->assertSame('10', $restored->rank);
        $this->assertSame('hearts', $restored->suit);
    }
}
