<?php

namespace Tests\Unit\Poker;

use App\Game\Poker\PokerEngine;
use App\Game\Poker\PokerState;
use PHPUnit\Framework\TestCase;

/**
 * Plays whole matches with random legal actions and checks the invariants a
 * real game must hold: chips are conserved, every hand terminates, betting
 * streets advance in order, and showdowns always have a full board.
 */
class PokerSimulationTest extends TestCase
{
    private PokerEngine $engine;

    private int $clock = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = 1_000_000;
        $this->engine = new PokerEngine(fn (): int => $this->clock);
    }

    private function totalChips(PokerState $state): int
    {
        return array_sum(array_map(fn ($p) => $p->chips + $p->totalBet, $state->players));
    }

    /**
     * @param  int  $aggression  0..100 chance of choosing raise/all-in when allowed
     * @return array{hands: int, streets: array<string,int>, showdowns: int}
     */
    private function playMatch(int $players, int $aggression, int $stack = 3000): array
    {
        $seats = [];
        for ($i = 1; $i <= $players; $i++) {
            $seats[] = ['user_id' => $i, 'seat' => $i - 1, 'chips' => $stack];
        }
        $state = $this->engine->deal($seats, 100);
        $total = $this->totalChips($state);
        $streets = ['preflop' => 0, 'flop' => 0, 'turn' => 0, 'river' => 0];
        $showdowns = 0;
        $hands = 1;
        $order = ['preflop' => 0, 'flop' => 1, 'turn' => 2, 'river' => 3];
        $lastRank = 0;

        for ($step = 0; $step < 20000 && $state->phase !== 'finished'; $step++) {
            $this->assertSame($total, $this->totalChips($state), 'chips must be conserved');

            if ($state->phase === 'hand_finished') {
                $this->clock += 30; // let the showdown play out
                if ($state->showdown) {
                    $showdowns++;
                    $this->assertCount(5, $state->community, 'a showdown always has a full board');
                }
                foreach ($state->players as $p) {
                    if ($p->chips > 0 && ! in_array($p->userId, $state->ready, true)) {
                        $state = $this->engine->applyAction($state, $p->userId, 'next_hand');
                    }
                }
                $hands++;
                $lastRank = 0;

                continue;
            }

            $this->assertArrayHasKey($state->phase, $order);
            $this->assertGreaterThanOrEqual($lastRank, $order[$state->phase], 'streets only move forward');
            $lastRank = $order[$state->phase];
            $streets[$state->phase]++;

            $actor = $state->players[$state->currentIndex]->userId;
            $allowed = $this->engine->getAllowedActions($state, $actor);
            $this->assertNotEmpty($allowed, 'the player to act always has a move');

            $roll = random_int(1, 100);
            if (in_array('raise', $allowed, true) && $roll <= $aggression) {
                $view = $this->engine->publicState($state, $actor);
                $amount = random_int(1, 10) === 1
                    ? $view['max_raise_to']
                    : random_int($view['min_raise_to'], min($view['max_raise_to'], $view['min_raise_to'] + 400));
                $state = $this->engine->applyAction($state, $actor, 'raise', ['amount' => $amount]);
            } elseif (in_array('check', $allowed, true)) {
                $state = $this->engine->applyAction($state, $actor, 'check');
            } elseif ($roll > 92) {
                $state = $this->engine->applyAction($state, $actor, 'fold');
            } else {
                $state = $this->engine->applyAction($state, $actor, 'call');
            }
        }

        $this->assertSame('finished', $state->phase, 'the match ends when one player has every chip');
        $this->assertSame($total, $this->totalChips($state));

        return ['hands' => $hands, 'streets' => $streets, 'showdowns' => $showdowns];
    }

    public function test_passive_tables_see_every_street_and_go_to_showdown(): void
    {
        $seen = ['flop' => 0, 'turn' => 0, 'river' => 0];
        $showdowns = 0;
        for ($i = 0; $i < 30; $i++) {
            $result = $this->playMatch(random_int(2, 6), 5, 1500);
            foreach (array_keys($seen) as $street) {
                $seen[$street] += $result['streets'][$street];
            }
            $showdowns += $result['showdowns'];
        }

        $this->assertGreaterThan(0, $seen['flop']);
        $this->assertGreaterThan(0, $seen['turn']);
        $this->assertGreaterThan(0, $seen['river']);
        $this->assertGreaterThan(0, $showdowns);
    }

    public function test_aggressive_tables_always_terminate_with_conserved_chips(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $result = $this->playMatch(random_int(2, 7), 55);
            $this->assertGreaterThan(0, $result['hands']);
        }
    }
}
