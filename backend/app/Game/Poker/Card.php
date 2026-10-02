<?php

namespace App\Game\Poker;

final class Card
{
    public const array RANKS = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];

    public const array SUITS = ['clubs', 'diamonds', 'hearts', 'spades'];

    public function __construct(
        public readonly string $rank,
        public readonly string $suit,
    ) {}

    /**
     * Numeric rank value: 2 → 2 … 10 → 10, J → 11, Q → 12, K → 13, A → 14.
     */
    public function value(): int
    {
        return array_search($this->rank, self::RANKS, true) + 2;
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
