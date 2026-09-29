<?php

namespace App\Game\Blackjack;

final class Card
{
    public const array RANKS = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];

    public const array SUITS = ['clubs', 'diamonds', 'hearts', 'spades'];

    public function __construct(
        public readonly string $rank,
        public readonly string $suit,
    ) {}

    public function isAce(): bool
    {
        return $this->rank === 'A';
    }

    public function baseValue(): int
    {
        return match ($this->rank) {
            'J', 'Q', 'K' => 10,
            'A' => 11,
            default => (int) $this->rank,
        };
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
