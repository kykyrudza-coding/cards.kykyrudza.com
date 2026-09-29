<?php

namespace App\Game\Blackjack;

final class BlackjackPlayer
{
    /** @var BlackjackHand[] */
    public array $hands;

    /**
     * @param  'active'|'out'  $status
     * @param  BlackjackHand[]  $hands
     */
    public function __construct(
        public int $userId,
        public int $seat,
        public int $chips,
        public string $status = 'active',
        array $hands = [],
    ) {
        $this->hands = $hands;
    }

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
            'hands' => array_map(fn (BlackjackHand $h) => $h->toArray(), $this->hands),
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
            array_map(fn (array $h) => BlackjackHand::fromArray($h), $data['hands']),
        );
    }
}
