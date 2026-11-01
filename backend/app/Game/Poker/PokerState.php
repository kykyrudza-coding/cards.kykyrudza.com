<?php

namespace App\Game\Poker;

use App\Game\Contracts\GameState;

final class PokerState implements GameState
{
    /**
     * @param  'preflop'|'flop'|'turn'|'river'|'hand_finished'|'finished'  $phase
     * @param  PokerPlayer[]  $players  Ordered by seat.
     * @param  Card[]  $community
     * @param  int  $currentBet  Highest street bet everyone must match.
     * @param  int  $minRaise  Smallest legal raise increment on this street.
     * @param  bool  $showdown  Whether the finished hand went to a showdown (reveals hands).
     * @param  array<int, array{user_id: int, amount: int, hand: ?string}>  $results
     * @param  int[]  $ready  userIds who've asked for the next hand.
     * @param  ?int  $runoutFrom  Board size when betting closed with an all-in runout (the rest is revealed street by street); null otherwise.
     * @param  array<int, array<string, mixed>>  $log  This hand's actions, oldest first.
     * @param  ?int  $turnDeadline  Unix time the player to act must act by.
     * @param  ?int  $nextHandAt  Unix time before which the next hand can't be requested (lets everyone watch the showdown).
     */
    public function __construct(
        public string $phase,
        public Deck $deck,
        public array $community,
        public array $players,
        public int $dealerIndex,
        public int $currentIndex,
        public int $currentBet,
        public int $minRaise,
        public int $bigBlind,
        public int $round,
        public bool $showdown = false,
        public array $results = [],
        public array $ready = [],
        public ?int $winnerId = null,
        public ?int $runoutFrom = null,
        public array $log = [],
        public ?int $turnDeadline = null,
        public int $turnSeconds = 45,
        public ?int $handEndedAt = null,
        public ?int $nextHandAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'phase' => $this->phase,
            'deck' => $this->deck->toArray(),
            'community' => array_map(fn (Card $c) => $c->toArray(), $this->community),
            'players' => array_map(fn (PokerPlayer $p) => $p->toArray(), $this->players),
            'dealer_index' => $this->dealerIndex,
            'current_index' => $this->currentIndex,
            'current_bet' => $this->currentBet,
            'min_raise' => $this->minRaise,
            'big_blind' => $this->bigBlind,
            'round' => $this->round,
            'showdown' => $this->showdown,
            'results' => $this->results,
            'ready' => $this->ready,
            'winner_id' => $this->winnerId,
            'runout_from' => $this->runoutFrom,
            'log' => $this->log,
            'turn_deadline' => $this->turnDeadline,
            'turn_seconds' => $this->turnSeconds,
            'hand_ended_at' => $this->handEndedAt,
            'next_hand_at' => $this->nextHandAt,
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
            array_map(fn (array $c) => Card::fromArray($c), $data['community']),
            array_map(fn (array $p) => PokerPlayer::fromArray($p), $data['players']),
            $data['dealer_index'],
            $data['current_index'],
            $data['current_bet'],
            $data['min_raise'],
            $data['big_blind'],
            $data['round'],
            $data['showdown'],
            $data['results'],
            $data['ready'],
            $data['winner_id'],
            $data['runout_from'] ?? null,
            $data['log'] ?? [],
            $data['turn_deadline'] ?? null,
            $data['turn_seconds'] ?? 45,
            $data['hand_ended_at'] ?? null,
            $data['next_hand_at'] ?? null,
        );
    }
}
