<?php

namespace App\Game\Blackjack;

use App\Game\Contracts\GameState;

final class BlackjackState implements GameState
{
    /**
     * @param  'dealing'|'player_turn'|'dealer_turn'|'settling'|'round_finished'  $phase
     * @param  Card[]  $dealerCards
     * @param  BlackjackPlayer[]  $players
     */
    public function __construct(
        public string $phase,
        public Deck $deck,
        public array $dealerCards,
        public bool $dealerHoleHidden,
        public array $players,
        public ?int $currentPlayerIndex,
        public ?int $currentHandIndex,
        public int $round,
        public array $confirmedBets = [],
        public bool $eventsEnabled = false,
        public ?int $machineGunHolderId = null,
        public ?array $eventResult = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'phase' => $this->phase,
            'deck' => $this->deck->toArray(),
            'dealer_cards' => array_map(fn (Card $c) => $c->toArray(), $this->dealerCards),
            'dealer_hole_hidden' => $this->dealerHoleHidden,
            'players' => array_map(fn (BlackjackPlayer $p) => $p->toArray(), $this->players),
            'current_player_index' => $this->currentPlayerIndex,
            'current_hand_index' => $this->currentHandIndex,
            'round' => $this->round,
            'confirmed_bets' => $this->confirmedBets,
            'events_enabled' => $this->eventsEnabled,
            'machine_gun_holder_id' => $this->machineGunHolderId,
            'event_result' => $this->eventResult,
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
            array_map(fn (array $c) => Card::fromArray($c), $data['dealer_cards']),
            $data['dealer_hole_hidden'],
            array_map(fn (array $p) => BlackjackPlayer::fromArray($p), $data['players']),
            $data['current_player_index'],
            $data['current_hand_index'],
            $data['round'],
            $data['confirmed_bets'] ?? [],
            $data['events_enabled'] ?? false,
            $data['machine_gun_holder_id'] ?? null,
            $data['event_result'] ?? null,
        );
    }
}
