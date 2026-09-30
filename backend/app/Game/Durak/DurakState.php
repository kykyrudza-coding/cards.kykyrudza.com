<?php

namespace App\Game\Durak;

use App\Game\Contracts\GameState;

final class DurakState implements GameState
{
    /**
     * @param  'attack'|'throw_in'|'finished'  $phase
     * @param  DurakPlayer[]  $players
     * @param  array<int, array{attack: Card, defense: ?Card}>  $table  Cards currently in play this turn.
     * @param  int[]  $passed  userIds who've declared they have nothing more to throw in, this turn.
     */
    public function __construct(
        public string $phase,
        public Deck $deck,
        public string $trumpSuit,
        public Card $trumpCard,
        public array $players,
        public array $table,
        public int $attackerIndex,
        public int $defenderIndex,
        public int $attackLimit,
        public int $round,
        public array $passed = [],
        public ?int $loserId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'phase' => $this->phase,
            'deck' => $this->deck->toArray(),
            'trump_suit' => $this->trumpSuit,
            'trump_card' => $this->trumpCard->toArray(),
            'players' => array_map(fn (DurakPlayer $p) => $p->toArray(), $this->players),
            'table' => array_map(fn (array $slot) => [
                'attack' => $slot['attack']->toArray(),
                'defense' => $slot['defense']?->toArray(),
            ], $this->table),
            'attacker_index' => $this->attackerIndex,
            'defender_index' => $this->defenderIndex,
            'attack_limit' => $this->attackLimit,
            'round' => $this->round,
            'passed' => $this->passed,
            'loser_id' => $this->loserId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['phase'],
            Deck::fromArrayData($data['deck']),
            $data['trump_suit'],
            Card::fromArray($data['trump_card']),
            array_map(fn (array $p) => DurakPlayer::fromArray($p), $data['players']),
            array_map(fn (array $slot) => [
                'attack' => Card::fromArray($slot['attack']),
                'defense' => $slot['defense'] !== null ? Card::fromArray($slot['defense']) : null,
            ], $data['table']),
            $data['attacker_index'],
            $data['defender_index'],
            $data['attack_limit'],
            $data['round'],
            $data['passed'] ?? [],
            $data['loser_id'] ?? null,
        );
    }
}
