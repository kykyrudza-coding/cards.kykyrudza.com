<?php

namespace App\Game\Durak;

final class DurakPlayer
{
    /** @var Card[] */
    public array $hand;

    /**
     * @param  'active'|'safe'  $status
     * @param  Card[]  $hand
     */
    public function __construct(
        public int $userId,
        public int $seat,
        public string $status = 'active',
        array $hand = [],
    ) {
        $this->hand = $hand;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'seat' => $this->seat,
            'status' => $this->status,
            'hand' => array_map(fn (Card $c) => $c->toArray(), $this->hand),
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
            $data['status'],
            array_map(fn (array $c) => Card::fromArray($c), $data['hand']),
        );
    }
}
