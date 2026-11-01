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
        $p1 = $this->player(1, 0, [new Card('7', 'diamonds')]); // can still throw in
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
        $p1 = $this->player(1, 0, [new Card('A', 'diamonds')]); // can still throw in
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
        $p3 = $this->player(3, 2, [new Card('K', 'spades'), new Card('Q', 'spades'), new Card('J', 'spades')]);
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

    // --- Revised rules: neighbours, taking, translating, clock ----------------

    private function fourPlayers(array $hands): array
    {
        $players = [];
        foreach ($hands as $i => $hand) {
            $players[] = $this->player($i + 1, $i, $hand);
        }

        return $players;
    }

    private function seven(string $suit): Card
    {
        return new Card('7', $suit);
    }

    public function test_only_the_two_neighbours_of_the_defender_may_throw_in(): void
    {
        // P1 attacks P2; P3 sits after the defender, P4 is across the table.
        $players = $this->fourPlayers([
            [$this->seven('diamonds')],
            [new Card('9', 'clubs')],
            [$this->seven('hearts')],
            [$this->seven('spades')],
        ]);
        $state = $this->state($players, table: [['attack' => $this->seven('clubs'), 'defense' => null]], trumpSuit: 'diamonds');

        $this->assertContains('attack', $this->engine->getAllowedActions($state, 1));
        $this->assertContains('attack', $this->engine->getAllowedActions($state, 3));
        $this->assertSame([], $this->engine->getAllowedActions($state, 4)); // P4 is not next to the defender

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 4, 'attack', ['cards' => [['rank' => '7', 'suit' => 'spades']]]);
    }

    public function test_throw_in_is_allowed_before_the_defender_has_beaten_anything(): void
    {
        $players = $this->fourPlayers([
            [$this->seven('diamonds')],
            [new Card('9', 'clubs')],
            [$this->seven('hearts')],
            [new Card('K', 'spades')],
        ]);
        $state = $this->state($players, table: [['attack' => $this->seven('clubs'), 'defense' => null]]);

        $this->engine->applyAction($state, 3, 'attack', ['cards' => [['rank' => '7', 'suit' => 'hearts']]]);

        $this->assertCount(2, $state->table);
    }

    public function test_opening_attack_may_use_several_cards_of_one_rank(): void
    {
        $players = $this->fourPlayers([
            [$this->seven('diamonds'), $this->seven('clubs'), new Card('K', 'hearts')],
            [new Card('9', 'clubs'), new Card('9', 'hearts'), new Card('9', 'spades')],
            [new Card('6', 'clubs')],
            [new Card('6', 'hearts')],
        ]);
        $state = $this->state($players);

        $this->engine->applyAction($state, 1, 'attack', ['cards' => [
            ['rank' => '7', 'suit' => 'diamonds'], ['rank' => '7', 'suit' => 'clubs'],
        ]]);

        $this->assertCount(2, $state->table);
    }

    public function test_opening_attack_with_mixed_ranks_is_rejected(): void
    {
        $players = $this->fourPlayers([
            [$this->seven('diamonds'), new Card('K', 'hearts')],
            [new Card('9', 'clubs'), new Card('9', 'hearts'), new Card('9', 'spades')],
            [new Card('6', 'clubs')],
            [new Card('6', 'hearts')],
        ]);
        $state = $this->state($players);

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 1, 'attack', ['cards' => [
            ['rank' => '7', 'suit' => 'diamonds'], ['rank' => 'K', 'suit' => 'hearts'],
        ]]);
    }

    public function test_two_cards_can_each_be_beaten_in_any_order(): void
    {
        $players = $this->fourPlayers([
            [$this->seven('diamonds')],
            [new Card('10', 'clubs'), new Card('10', 'hearts')],
            [new Card('6', 'clubs')],
            [new Card('6', 'hearts')],
        ]);
        $state = $this->state($players, table: [
            ['attack' => new Card('9', 'clubs'), 'defense' => null],
            ['attack' => new Card('9', 'hearts'), 'defense' => null],
        ]);

        // Beat the second card first.
        $this->engine->applyAction($state, 2, 'defend', [
            'attack' => ['rank' => '9', 'suit' => 'hearts'],
            'defense' => ['rank' => '10', 'suit' => 'hearts'],
        ]);
        $this->assertNull($state->table[0]['defense']);
        $this->assertSame('10', $state->table[1]['defense']->rank);

        $this->engine->applyAction($state, 2, 'defend', [
            'attack' => ['rank' => '9', 'suit' => 'clubs'],
            'defense' => ['rank' => '10', 'suit' => 'clubs'],
        ]);

        // Everything is beaten and nobody holds a 9 or 10, so the table goes to the discard.
        $this->assertSame([], $state->table);
        $this->assertSame(4, $state->discardCount);
    }

    public function test_defence_hints_list_every_card_that_beats_each_attack(): void
    {
        $players = $this->fourPlayers([
            [],
            [new Card('10', 'clubs'), new Card('J', 'clubs'), new Card('8', 'hearts')],
            [new Card('6', 'clubs')],
            [new Card('6', 'hearts')],
        ]);
        $state = $this->state($players, table: [
            ['attack' => new Card('9', 'clubs'), 'defense' => null],
            ['attack' => new Card('9', 'hearts'), 'defense' => null],
        ]);

        $hints = $this->engine->publicState($state, 2)['hints'];

        $this->assertCount(2, $hints['defenses']);
        $this->assertCount(2, $hints['defenses'][0]['with']); // 10♣ and J♣ beat 9♣
        $this->assertCount(0, $hints['defenses'][1]['with']); // nothing here beats 9♥ (8♥ is lower)
    }

    public function test_after_take_the_neighbours_may_still_add_cards_before_the_pile_is_picked_up(): void
    {
        $players = $this->fourPlayers([
            [$this->seven('diamonds')],
            [new Card('9', 'clubs')],
            [$this->seven('hearts')],
            [new Card('K', 'spades')],
        ]);
        $state = $this->state($players, table: [['attack' => $this->seven('clubs'), 'defense' => null]], attackLimit: 6);

        $this->engine->applyAction($state, 2, 'take');

        $this->assertTrue($state->taking);
        $this->assertCount(1, $state->table); // still on the table: the neighbours can add
        $this->assertContains('attack', $this->engine->getAllowedActions($state, 1));

        $this->engine->applyAction($state, 1, 'attack', ['cards' => [['rank' => '7', 'suit' => 'diamonds']]]);
        $this->engine->applyAction($state, 3, 'attack', ['cards' => [['rank' => '7', 'suit' => 'hearts']]]);

        // Nobody can add anything now, so the pile goes to the defender.
        $this->assertSame([], $state->table);
        $this->assertFalse($state->taking);
        $this->assertCount(4, $players[1]->hand); // own 9♣ + three sevens
    }

    public function test_the_defender_cannot_defend_after_saying_take(): void
    {
        $players = $this->fourPlayers([
            [$this->seven('diamonds')],
            [new Card('9', 'clubs')],
            [new Card('K', 'hearts')],
            [new Card('K', 'spades')],
        ]);
        $state = $this->state($players, table: [['attack' => $this->seven('clubs'), 'defense' => null]]);
        $this->engine->applyAction($state, 2, 'take');

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 2, 'defend', [
            'attack' => ['rank' => '7', 'suit' => 'clubs'],
            'defense' => ['rank' => '9', 'suit' => 'clubs'],
        ]);
    }

    public function test_the_turn_closes_by_itself_when_nobody_can_add_a_card(): void
    {
        $players = $this->fourPlayers([
            [new Card('K', 'hearts')],
            [new Card('9', 'clubs'), new Card('J', 'hearts')],
            [new Card('Q', 'hearts')],
            [new Card('A', 'hearts')],
        ]);
        $state = $this->state($players, table: [['attack' => $this->seven('clubs'), 'defense' => null]], deckCards: []);

        $this->engine->applyAction($state, 2, 'defend', [
            'attack' => ['rank' => '7', 'suit' => 'clubs'],
            'defense' => ['rank' => '9', 'suit' => 'clubs'],
        ]);

        $this->assertSame([], $state->table);
        $this->assertSame(2, $state->discardCount);
        $this->assertSame(1, $state->attackerIndex); // the defender attacks next
    }

    public function test_translate_can_be_repeated_all_the_way_back_to_the_attacker(): void
    {
        // P1 opens with 7♣, P2 translates to P3, P3 translates to P4, P4 translates back to P1.
        $players = $this->fourPlayers([
            [$this->seven('clubs'), new Card('K', 'hearts'), new Card('K', 'diamonds'), new Card('Q', 'hearts'), new Card('J', 'diamonds'), new Card('10', 'diamonds')],
            [$this->seven('diamonds'), new Card('K', 'clubs'), new Card('K', 'spades')],
            [$this->seven('hearts'), new Card('Q', 'clubs'), new Card('Q', 'spades'), new Card('J', 'clubs')],
            [$this->seven('spades'), new Card('J', 'spades'), new Card('J', 'diamonds'), new Card('10', 'clubs'), new Card('10', 'hearts')],
        ]);
        $state = $this->state($players, trumpSuit: 'hearts');
        $this->engine->applyAction($state, 1, 'attack', ['cards' => [['rank' => '7', 'suit' => 'clubs']]]);

        $this->engine->applyAction($state, 2, 'translate');
        $this->assertSame(2, $state->defenderIndex);
        $this->engine->applyAction($state, 3, 'translate');
        $this->assertSame(3, $state->defenderIndex);
        $this->engine->applyAction($state, 4, 'translate');

        $this->assertSame(0, $state->defenderIndex); // back round to P1, who opened
        $this->assertCount(4, $state->table);
        $this->assertSame(3, $state->attackerIndex);
    }

    public function test_translate_with_a_chosen_card_keeps_the_other_matching_cards(): void
    {
        $players = $this->fourPlayers([
            [new Card('K', 'hearts')],
            [$this->seven('diamonds'), $this->seven('hearts'), new Card('9', 'clubs')],
            [new Card('Q', 'clubs'), new Card('Q', 'spades'), new Card('J', 'clubs')],
            [new Card('J', 'spades')],
        ]);
        $state = $this->state($players, table: [['attack' => $this->seven('clubs'), 'defense' => null]]);

        $this->engine->applyAction($state, 2, 'translate', ['cards' => [['rank' => '7', 'suit' => 'diamonds']]]);

        $this->assertCount(2, $state->table);
        $this->assertCount(2, $players[1]->hand); // 7♥ and 9♣ stay
    }

    public function test_translate_skips_a_player_who_has_left_the_game(): void
    {
        $players = $this->fourPlayers([
            [new Card('K', 'hearts')],
            [$this->seven('diamonds'), new Card('9', 'clubs')],
            [],
            [new Card('J', 'spades'), new Card('J', 'clubs')],
        ]);
        $players[2]->status = 'safe';
        $state = $this->state($players, table: [['attack' => $this->seven('clubs'), 'defense' => null]], deckCards: []);

        $this->engine->applyAction($state, 2, 'translate');

        $this->assertSame(3, $state->defenderIndex); // P3 is out, so P4 is next
    }

    public function test_translate_is_refused_when_the_next_player_cannot_answer_the_table(): void
    {
        $players = $this->fourPlayers([
            [new Card('K', 'hearts')],
            [$this->seven('diamonds'), new Card('9', 'clubs')],
            [new Card('Q', 'clubs')], // only one card: cannot answer two
            [new Card('J', 'spades')],
        ]);
        $state = $this->state($players, table: [['attack' => $this->seven('clubs'), 'defense' => null]]);

        $this->assertNotContains('translate', $this->engine->getAllowedActions($state, 2));

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 2, 'translate');
    }

    public function test_translate_needs_three_players_still_in_the_game(): void
    {
        $players = $this->fourPlayers([
            [new Card('K', 'hearts')],
            [$this->seven('diamonds'), new Card('9', 'clubs')],
            [],
            [],
        ]);
        $players[2]->status = 'safe';
        $players[3]->status = 'safe';
        $state = $this->state($players, table: [['attack' => $this->seven('clubs'), 'defense' => null]], deckCards: []);

        $this->expectException(DurakActionException::class);
        $this->engine->applyAction($state, 2, 'translate');
    }

    public function test_the_player_after_the_defender_draws_back_up_after_throwing_in(): void
    {
        // P1 attacks P2; P3 threw in, so P3 must refill too (it used to be skipped).
        $players = $this->fourPlayers([
            [new Card('K', 'hearts')],
            [new Card('Q', 'hearts')],
            [new Card('J', 'hearts')],
            [new Card('10', 'hearts'), new Card('9', 'hearts'), new Card('8', 'hearts'), new Card('7', 'hearts'), new Card('6', 'hearts'), new Card('A', 'hearts')],
        ]);
        $deck = [];
        foreach (['6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'] as $rank) {
            $deck[] = new Card($rank, 'diamonds');
            $deck[] = new Card($rank, 'spades');
        }
        $state = $this->state($players, table: [['attack' => new Card('9', 'clubs'), 'defense' => new Card('10', 'clubs')]], deckCards: $deck);
        $state->passed = [1];

        $this->engine->applyAction($state, 3, 'pass');

        $this->assertCount(6, $players[0]->hand); // attacker first
        $this->assertCount(6, $players[2]->hand); // the other neighbour
        $this->assertCount(6, $players[1]->hand); // the defender last
    }

    public function test_the_clock_runs_the_turn_for_a_stalling_attacker(): void
    {
        $now = 1_000;
        $engine = new DurakEngine(function () use (&$now) {
            return $now;
        });
        $players = $this->fourPlayers([
            [new Card('K', 'hearts'), new Card('6', 'clubs'), new Card('A', 'spades')],
            [new Card('9', 'clubs')],
            [new Card('Q', 'hearts')],
            [new Card('J', 'hearts')],
        ]);
        $state = $this->state($players, trumpSuit: 'hearts');
        $state->turnDeadline = 1_060;

        $now = 1_030;
        try {
            $engine->applyAction($state, 3, 'timeout');
            $this->fail('The clock had not run out.');
        } catch (DurakActionException) {
        }

        $now = 1_061;
        $engine->applyAction($state, 3, 'timeout');

        $this->assertCount(1, $state->table);
        $this->assertSame('6', $state->table[0]['attack']->rank); // the weakest non-trump card
        $this->assertTrue($state->log[array_key_last($state->log)]['auto']);
        $this->assertSame(1_061 + $state->turnSeconds, $state->turnDeadline); // the clock restarted
    }

    public function test_the_clock_makes_a_stalling_defender_take(): void
    {
        $now = 1_000;
        $engine = new DurakEngine(function () use (&$now) {
            return $now;
        });
        $players = $this->fourPlayers([
            [new Card('K', 'hearts')],
            [new Card('9', 'clubs')],
            [new Card('Q', 'hearts')],
            [new Card('J', 'hearts')],
        ]);
        $state = $this->state($players, table: [['attack' => $this->seven('clubs'), 'defense' => null]]);
        $state->turnDeadline = 1_000;

        $now = 1_001;
        $engine->applyAction($state, 1, 'timeout');

        $this->assertSame([], $state->table); // taken, and nobody could add anything
        $this->assertCount(2, $players[1]->hand);
    }
}
