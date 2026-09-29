<?php

namespace App\Game\Blackjack;

final class BlackjackHand
{
    /** @var Card[] */
    public array $cards;

    /**
     * @param  Card[]  $cards
     * @param  'playing'|'stood'|'bust'|'blackjack'|'finished'  $status
     * @param  'win'|'lose'|'push'|'blackjack'|null  $result
     */
    public function __construct(
        array $cards,
        public int $bet,
        public string $status = 'playing',
        public ?string $result = null,
        public bool $isSplit = false,
        public ?int $profit = null,
    ) {
        $this->cards = $cards;
    }

    public function addCard(Card $card): void
    {
        $this->cards[] = $card;
    }

    /**
     * @return array{value: int, soft: bool}
     */
    public function score(): array
    {
        $total = 0;
        $aces = 0;

        foreach ($this->cards as $card) {
            $total += $card->baseValue();

            if ($card->isAce()) {
                $aces++;
            }
        }

        while ($total > 21 && $aces > 0) {
            $total -= 10;
            $aces--;
        }

        return ['value' => $total, 'soft' => $aces > 0];
    }

    public function isBust(): bool
    {
        return $this->score()['value'] > 21;
    }

    /**
     * A hand is Blackjack when it has exactly two cards: an Ace plus a
     * ten-value card (10/J/Q/K). Order and origin don't matter — a split
     * hand that ends up Ace + ten-value is Blackjack too. A 21 built from
     * three or more cards (e.g. 7+4+K) is never Blackjack.
     */
    public function isBlackjack(): bool
    {
        if (count($this->cards) !== 2) {
            return false;
        }

        $hasAce = false;
        $hasTenValueCard = false;

        foreach ($this->cards as $card) {
            if ($card->isAce()) {
                $hasAce = true;
            } elseif (in_array($card->rank, ['10', 'J', 'Q', 'K'], true)) {
                $hasTenValueCard = true;
            }
        }

        return $hasAce && $hasTenValueCard;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cards' => array_map(fn (Card $c) => $c->toArray(), $this->cards),
            'score' => $this->score()['value'],
            'bet' => $this->bet,
            'status' => $this->status,
            'result' => $this->result,
            'is_split' => $this->isSplit,
            'profit' => $this->profit,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map(fn (array $c) => Card::fromArray($c), $data['cards']),
            $data['bet'],
            $data['status'],
            $data['result'],
            $data['is_split'] ?? false,
            $data['profit'] ?? null,
        );
    }
}
