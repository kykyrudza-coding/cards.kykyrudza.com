<?php

namespace App\Game\Durak;

final class Deck
{
    /** @var Card[] */
    private array $cards;

    /**
     * @param  Card[]  $cards
     */
    private function __construct(array $cards)
    {
        $this->cards = array_values($cards);
    }

    public static function standard(): self
    {
        $cards = [];

        foreach (Card::SUITS as $suit) {
            foreach (Card::RANKS as $rank) {
                $cards[] = new Card($rank, $suit);
            }
        }

        return new self($cards);
    }

    /**
     * @param  Card[]  $cards
     */
    public static function fromCards(array $cards): self
    {
        return new self($cards);
    }

    public function shuffle(): self
    {
        for ($i = count($this->cards) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$this->cards[$i], $this->cards[$j]] = [$this->cards[$j], $this->cards[$i]];
        }

        return $this;
    }

    public function draw(): Card
    {
        $card = array_shift($this->cards);

        if ($card === null) {
            throw new \RuntimeException('Cannot draw from an empty deck.');
        }

        return $card;
    }

    /**
     * Appends a card to the bottom of the deck — used to slide the revealed
     * trump card back in once it's been drawn, so it's dealt last.
     */
    public function putOnBottom(Card $card): void
    {
        $this->cards[] = $card;
    }

    public function remaining(): int
    {
        return count($this->cards);
    }

    /**
     * @return array<int, array{rank: string, suit: string}>
     */
    public function toArray(): array
    {
        return array_map(fn (Card $card) => $card->toArray(), $this->cards);
    }

    /**
     * @param  array<int, array{rank: string, suit: string}>  $data
     */
    public static function fromArrayData(array $data): self
    {
        return new self(array_map(fn (array $c) => Card::fromArray($c), $data));
    }
}
