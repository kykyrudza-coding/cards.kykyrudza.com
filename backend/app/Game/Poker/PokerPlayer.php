<?php

namespace App\Game\Poker;

final class PokerPlayer
{
    /**
     * @param  'active'|'folded'|'all_in'|'out'  $status
     * @param  Card[]  $hand
     * @param  int  $bet  Chips committed on the current betting street.
     * @param  int  $totalBet  Chips committed over the whole hand (drives side pots).
     */
    public function __construct(
        public int $userId,
        public int $seat,
        public int $chips,
        public string $status = 'active',
        public array $hand = [],
        public int $bet = 0,
        public int $totalBet = 0,
        public bool $acted = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'seat' => $this->seat,
            'chips' => $this->chips,
            'status' => $this->status,
            'hand' => array_map(fn (Card $c) => $c->toArray(), $this->hand),
            'bet' => $this->bet,
            'total_bet' => $this->totalBet,
            'acted' => $this->acted,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['user_id'],
            $data['seat'],
            $data['chips'],
            $data['status'],
            array_map(fn (array $c) => Card::fromArray($c), $data['hand']),
            $data['bet'],
            $data['total_bet'],
            $data['acted'],
        );
    }
}
