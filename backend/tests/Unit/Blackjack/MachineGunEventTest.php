<?php

namespace Tests\Unit\Blackjack;

use App\Game\Blackjack\BlackjackActionException;
use App\Game\Blackjack\BlackjackEngine;
use App\Game\Blackjack\BlackjackState;
use App\Game\Blackjack\Card;
use App\Game\Blackjack\Deck;
use PHPUnit\Framework\TestCase;

class MachineGunEventTest extends TestCase
{
    /**
     * Three players betting 100/200/300; user 2 (seat 1) holds the machine gun.
     *
     * @param  int[]  $rolls  Values handed out by the injected RNG, in order.
     */
    private function table(array $rolls = [1, 1], bool $events = true): array
    {
        $engine = new BlackjackEngine(function () use (&$rolls): int {
            return array_shift($rolls) ?? 100;
        });
        $state = $engine->initial([
            ['user_id' => 1, 'seat' => 0, 'chips' => 1000],
            ['user_id' => 2, 'seat' => 1, 'chips' => 1000],
            ['user_id' => 3, 'seat' => 2, 'chips' => 1000],
        ], $events);
        $deck = Deck::fromCards(array_map(
            fn (string $r) => new Card($r, 'spades'),
            ['5', '6', '7', '9', '4', '3', '2', '8', '2', '2', '2', '2'],
        ));
        $state = $engine->nextRound($state, 100, $deck, [1 => 100, 2 => 200, 3 => 300]);

        return [$engine, $state];
    }

    public function test_nobody_gets_a_machine_gun_when_events_are_disabled(): void
    {
        [$engine, $state] = $this->table(events: false);

        $this->assertNull($state->machineGunHolderId);
    }

    public function test_roll_above_ten_percent_grants_nothing(): void
    {
        [, $state] = $this->table([11]);

        $this->assertNull($state->machineGunHolderId);
    }

    public function test_only_the_holder_sees_the_weapon(): void
    {
        [$engine, $state] = $this->table();

        $this->assertSame(2, $state->machineGunHolderId);
        $this->assertContains('machine_gun', $engine->publicState($state, 2)['allowed_actions'] ?? []);
        $this->assertSame([1, 3], $engine->publicState($state, 2)['event']['targets']);
        $this->assertNull($engine->publicState($state, 1)['event']);
        $this->assertNotContains('machine_gun', $engine->publicState($state, 1)['allowed_actions']);
    }

    public function test_killing_the_dealer_splits_all_bets_equally(): void
    {
        [$engine, $state] = $this->table();

        $state = $engine->applyAction($state, 2, 'machine_gun', ['mode' => 'dealer']);

        $this->assertSame('round_finished', $state->phase);
        // Pot 600 split three ways = 200 each; chips were 900/800/700 after betting.
        $this->assertSame([1100, 1000, 900], array_map(fn ($p) => $p->chips, $state->players));
        $this->assertSame('dealer', $state->eventResult['mode']);
        $this->assertNull($state->machineGunHolderId);
    }

    public function test_killing_a_player_splits_their_bet_between_the_others(): void
    {
        [$engine, $state] = $this->table();

        $state = $engine->applyAction($state, 2, 'machine_gun', ['mode' => 'player', 'target_id' => 3]);

        $this->assertSame('round_finished', $state->phase);
        // Victim (300) is split 150/150 on top of the survivors' refunded bets.
        $this->assertSame([1150, 1150, 700], array_map(fn ($p) => $p->chips, $state->players));
        $this->assertSame('killed', $state->players[2]->hands[0]->result);
        $this->assertSame(3, $state->eventResult['target_id']);
    }

    public function test_cannot_shoot_yourself_or_without_a_weapon(): void
    {
        [$engine, $state] = $this->table();

        try {
            $engine->applyAction($state, 2, 'machine_gun', ['mode' => 'player', 'target_id' => 2]);
            $this->fail('Expected exception');
        } catch (BlackjackActionException) {
            $this->assertSame('player_turn', $state->phase);
        }

        $this->expectException(BlackjackActionException::class);
        $engine->applyAction($state, 1, 'machine_gun', ['mode' => 'dealer']);
    }

    public function test_state_survives_serialization(): void
    {
        [, $state] = $this->table();

        $copy = BlackjackState::fromArray($state->toArray());

        $this->assertTrue($copy->eventsEnabled);
        $this->assertSame(2, $copy->machineGunHolderId);
    }
}
