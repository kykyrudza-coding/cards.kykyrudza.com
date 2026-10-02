<?php

namespace Tests\Unit\Poker;

use App\Game\Poker\Card;
use App\Game\Poker\HandEvaluator;
use PHPUnit\Framework\TestCase;

class HandEvaluatorTest extends TestCase
{
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

    private function handName(string $spec): string
    {
        return HandEvaluator::best($this->cards($spec))['name'];
    }

    private function beats(string $winner, string $loser): bool
    {
        return HandEvaluator::compare(
            HandEvaluator::best($this->cards($winner))['score'],
            HandEvaluator::best($this->cards($loser))['score'],
        ) > 0;
    }

    public function test_recognises_every_category(): void
    {
        $this->assertSame('high_card', $this->handName('2c 5d 9h Js Kc'));
        $this->assertSame('pair', $this->handName('2c 2d 9h Js Kc'));
        $this->assertSame('two_pair', $this->handName('2c 2d 9h 9s Kc'));
        $this->assertSame('three_of_a_kind', $this->handName('2c 2d 2h 9s Kc'));
        $this->assertSame('straight', $this->handName('5c 6d 7h 8s 9c'));
        $this->assertSame('flush', $this->handName('2c 5c 9c Jc Kc'));
        $this->assertSame('full_house', $this->handName('2c 2d 2h 9s 9c'));
        $this->assertSame('four_of_a_kind', $this->handName('2c 2d 2h 2s 9c'));
        $this->assertSame('straight_flush', $this->handName('5h 6h 7h 8h 9h'));
    }

    public function test_ace_plays_low_in_the_wheel_and_the_wheel_is_the_lowest_straight(): void
    {
        $this->assertSame('straight', $this->handName('Ac 2d 3h 4s 5c'));
        $this->assertTrue($this->beats('2c 3d 4h 5s 6c', 'Ac 2d 3h 4s 5c'));
    }

    public function test_picks_the_best_five_of_seven(): void
    {
        $this->assertSame('full_house', $this->handName('Ac Ad Ah Kc Kd 2s 7h'));
        $this->assertSame('flush', $this->handName('2h 5h 9h Jh Kh Ks Kd'));
        $this->assertSame('straight', $this->handName('4c 5d 6h 7s 8c Kd Kh'));
    }

    public function test_ranks_hands_across_categories(): void
    {
        $this->assertTrue($this->beats('2c 2d 2h 9s 9c', '2c 5c 9c Jc Kc')); // full house > flush
        $this->assertTrue($this->beats('2c 5c 9c Jc Kc', '5c 6d 7h 8s 9c')); // flush > straight
        $this->assertTrue($this->beats('2c 2d 9h 9s Kc', 'Ac Ad 3h 4s 5c')); // two pair > pair
    }

    public function test_breaks_ties_with_kickers_and_detects_exact_ties(): void
    {
        $this->assertTrue($this->beats('Ac Ad Kh 3s 2c', 'Ac Ad Qh 3s 2c'));
        $this->assertTrue($this->beats('Kc Kd Ah 3s 2c', 'Qc Qd Ah 3s 2c'));

        $tie = HandEvaluator::compare(
            HandEvaluator::best($this->cards('Ac Kd Qh Js 9c'))['score'],
            HandEvaluator::best($this->cards('Ad Kh Qs Jc 9d'))['score'],
        );
        $this->assertSame(0, $tie);
    }
}
