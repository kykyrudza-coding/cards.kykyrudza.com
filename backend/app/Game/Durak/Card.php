<?php

namespace App\Game\Durak;

final class Card
{
    public const array RANKS = ['6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];

    public const array SUITS = ['clubs', 'diamonds', 'hearts', 'spades'];

    public function __construct(
        public readonly string $rank,
        public readonly string $suit,
    ) {}

    /**
     * Position of this card's rank within RANKS (0 = lowest, i.e. '6').
     * Used to compare two same-suit cards.
     */
    public function strength(): int
    {
        return array_search($this->rank, self::RANKS, true);
    }

    /**
     * @return array{rank: string, suit: string}
     */
    public function toArray(): array
    {
        return ['rank' => $this->rank, 'suit' => $this->suit];
    }

    /**
     * @param  array{rank: string, suit: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['rank'], $data['suit']);
    }
}
