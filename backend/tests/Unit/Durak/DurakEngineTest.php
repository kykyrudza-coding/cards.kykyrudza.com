<?php

namespace Tests\Unit\Durak;

use App\Game\Durak\Card;
use App\Game\Durak\Deck;
use App\Game\Durak\DurakActionException;
use App\Game\Durak\DurakEngine;
use App\Game\Durak\DurakPlayer;
use App\Game\Durak\DurakState;
use PHPUnit\Framework\TestCase;

class DurakEngineTest extends TestCase
{
    private DurakEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new DurakEngine;
    }

    /**
     * @param  array<int, array{user_id:int, seat:int}>  $matchPlayers
     * @param  Card[]  $cards  Drawn in order: 6 per player round-robin, then the trump card.
     */
    private function deal(array $matchPlayers, array $cards): DurakState
    {
        return $this->engine->deal($matchPlayers, Deck::fromCards($cards));
    }

    private function twoPlayers(): array
    {
        return [['user_id' => 1, 'seat' => 0], ['user_id' => 2, 'seat' => 1]];
    }

    private function threePlayers(): array
    {
        return [['user_id' => 1, 'seat' => 0], ['user_id' => 2, 'seat' => 1], ['user_id' => 3, 'seat' => 2]];
    }

    /**
     * @param  DurakPlayer[]  $players
     * @param  array<int, array{attack: Card, defense: ?Card}>  $table
     * @param  Card[]  $deckCards
     */
    private function state(
        array $players,
        array $table = [],
        int $attackerIndex = 0,
        ?int $defenderIndex = null,
        string $trumpSuit = 'spades',
        array $deckCards = [],
        array $passed = [],
        string $phase = 'attack',
        ?int $attackLimit = null,
    ): DurakState {
        $defenderIndex ??= ($attackerIndex + 1) % count($players);

        return new DurakState(
            phase: $phase,
            deck: Deck::fromCards($deckCards),
            trumpSuit: $trumpSuit,
            trumpCard: new Card('6', $trumpSuit),
            players: $players,
            table: $table,
            attackerIndex: $attackerIndex,
            defenderIndex: $defenderIndex,
            attackLimit: $attackLimit ?? 6,
            round: 1,
            passed: $passed,
        );
    }

    private function player(int $userId, int $seat, array $hand, string $status = 'active'): DurakPlayer
    {
        return new DurakPlayer($userId, $seat, $status, $hand);
    }

    // --- Deal ----------------------------------------------------------------

    public function test_deal_gives_six_cards_each_and_sets_trump_from_next_card(): void
    {
        $cards = [];
        foreach (['6', '7', '8', '9', '10', 'J'] as $rank) {
            $cards[] = new Card($rank, 'clubs');
            $cards[] = new Card($rank, 'diamonds');
        }
        $cards[] = new Card('Q', 'spades'); // trump

        $state = $this->deal($this->twoPlayers(), $cards);

        $this->assertCount(6, $state->players[0]->hand);
        $this->assertCount(6, $state->players[1]->hand);
        $this->assertSame('spades', $state->trumpSuit);
        $this->assertSame('Q', $state->trumpCard->rank);
        // trump card slides to the bottom of the deck, still drawable last
        $this->assertSame(1, $state->deck->remaining());
        $this->assertSame('attack', $state->phase);
    }

    public function test_first_attacker_holds_the_lowest_trump(): void
    {
        $cards = [
            new Card('K', 'spades'), new Card('8', 'spades'), // P1c1, P2c1 (P2 holds a lower trump)
            new Card('7', 'clubs'), new Card('9', 'clubs'),
            new Card('8', 'clubs'), new Card('10', 'clubs'),
            new Card('9', 'clubs'), new Card('J', 'clubs'),
            new Card('10', 'clubs'), new Card('Q', 'clubs'),
            new Card('J', 'clubs'), new Card('K', 'clubs'),
            new Card('A', 'spades'), // trump suit spades
        ];

        $state = $this->deal($this->twoPlayers(), $cards);

        // P2 (index 1) holds the 8 of spades, the lowest trump in play.
        $this->assertSame(1, $state->attackerIndex);
        $this->assertSame(0, $state->defenderIndex);
    }

    // --- Opening attack --------------------------------------------------------

    public function test_opening_attack_must_be_a_single_card_by_the_attacker(): void
    {
        $p1 = $this->player(1, 0, [new Card('7', 'clubs'), new Card('8', 'clubs')]);
        $p2 = $this->player(2, 1, [new Card('9', 'clubs')]);
        $state = $this->state([$p1, $p2]);

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 1, 'attack', ['cards' => [
            ['rank' => '7', 'suit' => 'clubs'], ['rank' => '8', 'suit' => 'clubs'],
        ]]);
    }

    public function test_only_the_attacker_can_open_the_turn(): void
    {
        $p1 = $this->player(1, 0, [new Card('7', 'clubs')]);
        $p2 = $this->player(2, 1, [new Card('9', 'clubs')]);
        $p3 = $this->player(3, 2, [new Card('J', 'clubs')]);
        $state = $this->state([$p1, $p2, $p3], attackerIndex: 0, defenderIndex: 1);

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 3, 'attack', ['cards' => [['rank' => 'J', 'suit' => 'clubs']]]);
    }

    // --- Defend ------------------------------------------------------------

    public function test_defender_beats_with_a_higher_card_of_the_same_suit(): void
    {
        $p1 = $this->player(1, 0, []);
        $p2 = $this->player(2, 1, [new Card('9', 'clubs')]);
        $state = $this->state([$p1, $p2], table: [['attack' => new Card('7', 'clubs'), 'defense' => null]]);

        $this->engine->applyAction($state, 2, 'defend', [
            'attack' => ['rank' => '7', 'suit' => 'clubs'],
            'defense' => ['rank' => '9', 'suit' => 'clubs'],
        ]);

        $this->assertSame('9', $state->table[0]['defense']->rank);
        $this->assertCount(0, $p2->hand);
    }

    public function test_trump_beats_a_non_trump_attack(): void
    {
        $p1 = $this->player(1, 0, []);
        $p2 = $this->player(2, 1, [new Card('6', 'spades')]);
        $state = $this->state([$p1, $p2], table: [['attack' => new Card('A', 'clubs'), 'defense' => null]], trumpSuit: 'spades');

        $this->engine->applyAction($state, 2, 'defend', [
            'attack' => ['rank' => 'A', 'suit' => 'clubs'],
            'defense' => ['rank' => '6', 'suit' => 'spades'],
        ]);

        $this->assertNotNull($state->table[0]['defense']);
    }

    public function test_defend_rejects_a_card_that_does_not_beat_the_attack(): void
    {
        $p1 = $this->player(1, 0, []);
        $p2 = $this->player(2, 1, [new Card('6', 'clubs')]);
        $state = $this->state([$p1, $p2], table: [['attack' => new Card('7', 'clubs'), 'defense' => null]]);

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 2, 'defend', [
            'attack' => ['rank' => '7', 'suit' => 'clubs'],
            'defense' => ['rank' => '6', 'suit' => 'clubs'],
        ]);
    }

    public function test_cannot_defend_out_of_turn(): void
    {
        $p1 = $this->player(1, 0, [new Card('9', 'clubs')]);
        $p2 = $this->player(2, 1, []);
        $state = $this->state([$p1, $p2], table: [['attack' => new Card('7', 'clubs'), 'defense' => null]]);

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 1, 'defend', [
            'attack' => ['rank' => '7', 'suit' => 'clubs'],
            'defense' => ['rank' => '9', 'suit' => 'clubs'],
        ]);
    }

    // --- Throw-in ------------------------------------------------------------

    public function test_throw_in_requires_a_rank_already_on_the_table(): void
    {
        $p1 = $this->player(1, 0, [new Card('9', 'diamonds')]);
        $p2 = $this->player(2, 1, []);
        $state = $this->state([$p1, $p2], table: [
            ['attack' => new Card('7', 'clubs'), 'defense' => new Card('8', 'clubs')],
        ]);

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 1, 'attack', ['cards' => [['rank' => '9', 'suit' => 'diamonds']]]);
    }

    public function test_throw_in_of_a_matching_rank_is_accepted_and_reopens_the_table(): void
    {
        $p1 = $this->player(1, 0, [new Card('7', 'diamonds')]);
        $p2 = $this->player(2, 1, []);
        $state = $this->state([$p1, $p2], table: [
            ['attack' => new Card('7', 'clubs'), 'defense' => new Card('8', 'clubs')],
        ], passed: [1]);

        $this->engine->applyAction($state, 1, 'attack', ['cards' => [['rank' => '7', 'suit' => 'diamonds']]]);

        $this->assertCount(2, $state->table);
        $this->assertNull($state->table[1]['defense']);
        // adding a new card gives everyone another chance to react
        $this->assertSame([], $state->passed);
    }

    public function test_bito_resolves_once_everyone_passes_and_refills_hands(): void
    {
        $p1 = $this->player(1, 0, []);
        $p2 = $this->player(2, 1, []);
        $deckCards = [new Card('6', 'clubs'), new Card('7', 'clubs')];
        $state = $this->state(
            [$p1, $p2],
            table: [['attack' => new Card('9', 'clubs'), 'defense' => new Card('10', 'clubs')]],
            deckCards: $deckCards,
        );

        $this->engine->applyAction($state, 1, 'pass');

        $this->assertSame([], $state->table);
        $this->assertSame(1, $state->attackerIndex); // old defender (P2) becomes new attacker
        $this->assertSame(0, $state->defenderIndex);
        $this->assertSame(2, $state->round);
        // P1 (new defender) draws first per turn order starting at the old attacker
        $this->assertCount(1, $p1->hand);
        $this->assertCount(1, $p2->hand);
    }

    // --- Take ------------------------------------------------------------------

    public function test_take_moves_all_table_cards_to_the_defenders_hand_and_skips_their_turn(): void
    {
        // Non-empty filler hands for the bystanders: with the deck empty,
        // a genuinely empty hand would mark that player "safe" and end the
        // game — irrelevant to what this test is checking.
        $p1 = $this->player(1, 0, [new Card('6', 'hearts')]);
        $p2 = $this->player(2, 1, []);
        $p3 = $this->player(3, 2, [new Card('6', 'diamonds')]);
        $state = $this->state(
            [$p1, $p2, $p3],
            table: [['attack' => new Card('9', 'clubs'), 'defense' => null]],
            attackerIndex: 0,
            defenderIndex: 1,
        );

        $this->engine->applyAction($state, 2, 'take');

        $this->assertSame([], $state->table);
        $this->assertCount(1, $p2->hand);
        $this->assertSame('9', $p2->hand[0]->rank);
        // taker is skipped: attacker after P1 is P3, not P2
        $this->assertSame(2, $state->attackerIndex);
        $this->assertSame(0, $state->defenderIndex);
    }

    public function test_take_is_rejected_once_everything_is_already_defended(): void
    {
        $p1 = $this->player(1, 0, []);
        $p2 = $this->player(2, 1, []);
        $state = $this->state([$p1, $p2], table: [
            ['attack' => new Card('9', 'clubs'), 'defense' => new Card('10', 'clubs')],
        ]);

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 2, 'take');
    }

    // --- Translate ---------------------------------------------------------

    public function test_translate_needs_at_least_three_players(): void
    {
        $p1 = $this->player(1, 0, []);
        $p2 = $this->player(2, 1, [new Card('7', 'diamonds')]);
        $state = $this->state([$p1, $p2], table: [['attack' => new Card('7', 'clubs'), 'defense' => null]]);

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 2, 'translate');
    }

    public function test_translate_passes_defender_duty_using_all_matching_cards(): void
    {
        $p1 = $this->player(1, 0, []);
        $p2 = $this->player(2, 1, [new Card('7', 'diamonds'), new Card('7', 'hearts')]);
        $p3 = $this->player(3, 2, [new Card('K', 'spades')]);
        $state = $this->state(
            [$p1, $p2, $p3],
            table: [['attack' => new Card('7', 'clubs'), 'defense' => null]],
            attackerIndex: 0,
            defenderIndex: 1,
        );

        $this->engine->applyAction($state, 2, 'translate');

        $this->assertCount(3, $state->table); // original + both translated 7s
        $this->assertSame(2, $state->defenderIndex); // P3 is now the defender
        $this->assertCount(0, $p2->hand);
    }

    public function test_translate_is_only_possible_before_any_defense(): void
    {
        $p1 = $this->player(1, 0, []);
        $p2 = $this->player(2, 1, [new Card('7', 'diamonds')]);
        $p3 = $this->player(3, 2, []);
        $state = $this->state(
            [$p1, $p2, $p3],
            table: [
                ['attack' => new Card('9', 'clubs'), 'defense' => new Card('10', 'clubs')],
                ['attack' => new Card('7', 'clubs'), 'defense' => null],
            ],
            attackerIndex: 0,
            defenderIndex: 1,
        );

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 2, 'translate');
    }

    // --- Win condition ---------------------------------------------------------

    public function test_last_player_holding_cards_once_the_deck_is_empty_is_the_durak(): void
    {
        $p1 = $this->player(1, 0, []); // about to empty out
        $p2 = $this->player(2, 1, [new Card('7', 'clubs')]);
        $state = $this->state(
            [$p1, $p2],
            table: [['attack' => new Card('9', 'clubs'), 'defense' => new Card('10', 'clubs')]],
            deckCards: [], // nothing left to draw
        );

        $this->engine->applyAction($state, 1, 'pass');

        $this->assertSame('finished', $state->phase);
        $this->assertSame(2, $state->loserId);
    }

    // --- Allowed actions -----------------------------------------------------

    public function test_allowed_actions_reflect_whose_turn_it_is(): void
    {
        $p1 = $this->player(1, 0, [new Card('7', 'clubs')]);
        $p2 = $this->player(2, 1, []);
        $state = $this->state([$p1, $p2]);

        $this->assertSame(['attack'], $this->engine->getAllowedActions($state, 1));
        $this->assertSame([], $this->engine->getAllowedActions($state, 2));
    }

    public function test_public_state_hides_opponent_hands(): void
    {
        $p1 = $this->player(1, 0, [new Card('7', 'clubs')]);
        $p2 = $this->player(2, 1, [new Card('8', 'clubs'), new Card('9', 'clubs')]);
        $state = $this->state([$p1, $p2]);

        $view = $this->engine->publicState($state, 1);

        $this->assertArrayHasKey('hand', $view['players'][0]);
        $this->assertArrayNotHasKey('hand', $view['players'][1]);
        $this->assertSame(2, $view['players'][1]['hand_count']);
    }
}
