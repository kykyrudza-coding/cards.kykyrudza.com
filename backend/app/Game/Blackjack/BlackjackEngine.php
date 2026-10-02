<?php

namespace App\Game\Blackjack;

use App\Game\Contracts\GameEngine;

/**
 * Pure Blackjack rules engine. Knows nothing about HTTP, the database,
 * Eloquent or broadcasting — it only transforms a BlackjackState given an
 * action, and is fully deterministic given a fixed Deck.
 */
final class BlackjackEngine implements GameEngine
{
    /** Per-round chance (percent) that the Machine Gun event drops on a random player. */
    public const int MACHINE_GUN_CHANCE = 10;

    /** @var \Closure(int, int): int */
    private readonly \Closure $random;

    /**
     * @param  (\Closure(int, int): int)|null  $random  Inject a predictable RNG for tests.
     */
    public function __construct(?\Closure $random = null)
    {
        $this->random = $random ?? fn (int $min, int $max): int => random_int($min, $max);
    }

    /**
     * @param  array<int, array{user_id: int, seat: int, chips: int}>  $matchPlayers
     * @param  Deck|null  $deck  Inject a predefined deck for deterministic tests; omit for real play.
     */
    public function deal(array $matchPlayers, int $defaultBet, int $round = 1, ?Deck $deck = null): BlackjackState
    {
        $players = array_map(
            fn (array $p) => new BlackjackPlayer($p['user_id'], $p['seat'], $p['chips']),
            $matchPlayers
        );
        usort($players, fn (BlackjackPlayer $a, BlackjackPlayer $b) => $a->seat <=> $b->seat);

        $state = new BlackjackState(
            phase: 'dealing',
            deck: $deck ?? Deck::standard()->shuffle(),
            dealerCards: [],
            dealerHoleHidden: true,
            players: $players,
            currentPlayerIndex: null,
            currentHandIndex: null,
            round: $round,
        );

        $this->dealRound($state, $defaultBet);

        return $state;
    }

    /**
     * Seats players at the table with empty hands, in the same
     * 'round_finished' phase a round normally ends in — so the very first
     * hand goes through the same bet-confirmation flow as every other
     * round (MatchService::placeBet), instead of auto-dealing with the
     * lobby's default_bet before anyone has agreed to a stake.
     *
     * @param  array<int, array{user_id: int, seat: int, chips: int}>  $matchPlayers
     */
    public function initial(array $matchPlayers, bool $eventsEnabled = false): BlackjackState
    {
        $players = array_map(
            fn (array $p) => new BlackjackPlayer($p['user_id'], $p['seat'], $p['chips']),
            $matchPlayers
        );
        usort($players, fn (BlackjackPlayer $a, BlackjackPlayer $b) => $a->seat <=> $b->seat);

        return new BlackjackState(
            phase: 'round_finished',
            deck: Deck::standard()->shuffle(),
            dealerCards: [],
            dealerHoleHidden: true,
            players: $players,
            currentPlayerIndex: null,
            currentHandIndex: null,
            round: 0,
            eventsEnabled: $eventsEnabled,
        );
    }

    public function applyAction(BlackjackState $state, int $userId, string $action, array $payload = []): BlackjackState
    {
        if ($state->phase !== 'player_turn') {
            throw new BlackjackActionException('No action can be taken right now.');
        }

        if ($action === 'machine_gun') {
            $this->applyMachineGun($state, $userId, $payload);

            return $state;
        }

        [$player, $hand] = $this->requireCurrentPlayerHand($state, $userId);

        match ($action) {
            'hit' => $this->applyHit($state, $hand),
            'stand' => $this->applyStand($hand),
            'double' => $this->applyDouble($state, $player, $hand),
            'split' => $this->applySplit($state, $player, $hand),
            default => throw new BlackjackActionException("Unknown action: {$action}"),
        };

        $this->advanceIfCurrentHandFinished($state);

        if ($state->phase === 'dealer_turn') {
            $this->playDealer($state);
            $this->settle($state);
        }

        return $state;
    }

    public function nextRound(BlackjackState $state, int $defaultBet, ?Deck $deck = null, array $bets = []): BlackjackState
    {
        if ($state->phase !== 'round_finished') {
            throw new BlackjackActionException('Current round is not finished yet.');
        }

        $state->round++;
        $state->deck = $deck ?? Deck::standard()->shuffle();
        $state->phase = 'dealing';
        $state->currentPlayerIndex = null;
        $state->currentHandIndex = null;

        $this->dealRound($state, $defaultBet, $bets);
        $state->confirmedBets = [];

        return $state;
    }

    public function getAllowedActions(BlackjackState $state, int $userId): array
    {
        if ($state->phase !== 'player_turn' || $state->currentPlayerIndex === null) {
            return [];
        }

        $player = $state->players[$state->currentPlayerIndex];

        if ($player->userId !== $userId) {
            return $this->machineGunActions($state, $userId);
        }

        $hand = $player->hands[$state->currentHandIndex];

        return array_merge(BlackjackRules::allowedActions($hand, $player), $this->machineGunActions($state, $userId));
    }

    /**
     * The Machine Gun holder may fire at any moment of the round, not only on
     * their own turn.
     *
     * @return string[]
     */
    private function machineGunActions(BlackjackState $state, int $userId): array
    {
        return $state->phase === 'player_turn' && $state->machineGunHolderId === $userId ? ['machine_gun'] : [];
    }

    public function isRoundFinished(BlackjackState $state): bool
    {
        return $state->phase === 'round_finished';
    }

    /**
     * Public, per-viewer serialization: redacts the dealer's hole card while
     * hidden and computes allowed_actions relative to the viewer only.
     *
     * @return array<string, mixed>
     */
    public function publicState(mixed $state, int $viewerId): array
    {
        $state = $this->castState($state);

        $dealerCards = [];
        foreach ($state->dealerCards as $i => $card) {
            $dealerCards[] = ($state->dealerHoleHidden && $i === 1)
                ? ['hidden' => true]
                : $card->toArray();
        }

        $dealerScore = $state->dealerHoleHidden
            ? null
            : (new BlackjackHand($state->dealerCards, 0))->score()['value'];

        $currentPlayerId = $state->currentPlayerIndex !== null
            ? $state->players[$state->currentPlayerIndex]->userId
            : null;

        $players = [];
        foreach ($state->players as $pi => $player) {
            $hands = [];
            foreach ($player->hands as $hi => $hand) {
                $data = $hand->toArray();
                $data['is_active'] = $state->currentPlayerIndex === $pi && $state->currentHandIndex === $hi;
                $hands[] = $data;
            }

            $players[] = [
                'id' => $player->userId,
                'seat' => $player->seat,
                'chips' => $player->chips,
                'status' => $player->status,
                'hands' => $hands,
            ];
        }

        return [
            'phase' => $state->phase,
            'round' => $state->round,
            'dealer' => [
                'cards' => $dealerCards,
                'score' => $dealerScore,
                'status' => $state->dealerHoleHidden ? null : ((new BlackjackHand($state->dealerCards, 0))->isBlackjack() ? 'blackjack' : ($dealerScore > 21 ? 'bust' : 'stood')),
            ],
            'players' => $players,
            'current_player_id' => $currentPlayerId,
            'current_hand_index' => $state->currentHandIndex,
            'allowed_actions' => $this->getAllowedActions($state, $viewerId),
            // Only the holder ever learns the weapon exists, and only until it is fired.
            'event' => $this->machineGunActions($state, $viewerId) === [] ? null : [
                'type' => 'machine_gun',
                'targets' => $this->machineGunTargets($state, $viewerId),
            ],
            'event_result' => $state->eventResult,
        ];
    }

    private function dealRound(BlackjackState $state, int $defaultBet, array $bets = []): void
    {
        foreach ($state->players as $player) {
            $bet = $bets[$player->userId] ?? $defaultBet;
            if ($player->status === 'out' || $player->chips < $bet) {
                $player->status = 'out';
                $player->hands = [];

                continue;
            }

            $player->status = 'active';
            $player->chips -= $bet;
            $player->hands = [new BlackjackHand([], $bet)];
        }

        $state->dealerCards = [];
        $state->dealerHoleHidden = true;
        $state->machineGunHolderId = null;
        $state->eventResult = null;

        for ($i = 0; $i < 2; $i++) {
            foreach ($state->players as $player) {
                if ($player->status !== 'active') {
                    continue;
                }
                $player->hands[0]->addCard($state->deck->draw());
            }
            $state->dealerCards[] = $state->deck->draw();
        }

        foreach ($state->players as $player) {
            if ($player->status !== 'active') {
                continue;
            }
            if ($player->hands[0]->isBlackjack()) {
                $player->hands[0]->status = 'blackjack';
            }
        }

        // Dealer Blackjack is checked immediately after the deal, before any
        // player gets to act — real casinos never let players play out a
        // hand the dealer has already won outright with a natural.
        if ((new BlackjackHand($state->dealerCards, 0))->isBlackjack()) {
            $state->phase = 'dealer_turn';
            $this->playDealer($state);
            $this->settle($state);

            return;
        }

        $state->phase = 'player_turn';
        $this->advanceToNextPlayableHand($state, -1, -1);

        if ($state->phase === 'dealer_turn') {
            $this->playDealer($state);
            $this->settle($state);

            return;
        }

        $this->maybeGrantMachineGun($state);
    }

    private function maybeGrantMachineGun(BlackjackState $state): void
    {
        if (! $state->eventsEnabled || ($this->random)(1, 100) > self::MACHINE_GUN_CHANCE) {
            return;
        }

        $holders = array_values(array_filter($state->players, fn (BlackjackPlayer $p) => $this->isInRound($p)));

        if ($holders === []) {
            return;
        }

        $state->machineGunHolderId = $holders[($this->random)(0, count($holders) - 1)]->userId;
    }

    private function isInRound(BlackjackPlayer $player): bool
    {
        return $player->status === 'active' && $player->hands !== [];
    }

    /**
     * @return int[]
     */
    private function machineGunTargets(BlackjackState $state, int $holderId): array
    {
        return array_values(array_map(
            fn (BlackjackPlayer $p) => $p->userId,
            array_filter($state->players, fn (BlackjackPlayer $p) => $p->userId !== $holderId && $this->isInRound($p)),
        ));
    }

    /**
     * Dealer kill: every bet goes into one pot split equally between all
     * players. Player kill: the victim forfeits their bet, which is split
     * equally between the surviving players on top of their own refunded bets.
     * Either way the round ends on the spot. Remainders go to the earliest seats.
     */
    private function applyMachineGun(BlackjackState $state, int $userId, array $payload): void
    {
        if ($state->machineGunHolderId !== $userId) {
            throw new BlackjackActionException('You do not have the machine gun.', 403);
        }

        $mode = $payload['mode'] ?? null;
        $participants = array_values(array_filter($state->players, fn (BlackjackPlayer $p) => $this->isInRound($p)));
        $bets = [];
        foreach ($participants as $p) {
            $bets[$p->userId] = array_sum(array_map(fn (BlackjackHand $h) => $h->bet, $p->hands));
        }

        $victimId = null;
        if ($mode === 'dealer') {
            $recipients = $participants;
            $pot = array_sum($bets);
            $refunds = array_fill_keys(array_keys($bets), 0);
        } elseif ($mode === 'player') {
            $victimId = isset($payload['target_id']) ? (int) $payload['target_id'] : null;
            if ($victimId === null || ! in_array($victimId, $this->machineGunTargets($state, $userId), true)) {
                throw new BlackjackActionException('Choose another player at the table.');
            }
            $recipients = array_values(array_filter($participants, fn (BlackjackPlayer $p) => $p->userId !== $victimId));
            $pot = $bets[$victimId];
            $refunds = $bets;
            $refunds[$victimId] = 0;
        } else {
            throw new BlackjackActionException('Choose the dealer or a player.');
        }

        $share = intdiv($pot, count($recipients));
        $remainder = $pot % count($recipients);

        foreach ($participants as $p) {
            $payout = 0;
            $recipientIndex = array_search($p, $recipients, true);
            if ($recipientIndex !== false) {
                $payout = ($refunds[$p->userId] ?? 0) + $share + ($recipientIndex < $remainder ? 1 : 0);
            }

            $p->chips += $payout;
            $killed = $p->userId === $victimId;
            foreach ($p->hands as $i => $hand) {
                $hand->status = $hand->status === 'playing' ? 'finished' : $hand->status;
                $hand->result = $killed ? 'killed' : 'event';
                $hand->profit = ($i === 0 ? $payout : 0) - $hand->bet;
            }
        }

        $state->eventResult = [
            'type' => 'machine_gun',
            'holder_id' => $userId,
            'mode' => $mode,
            'target_id' => $victimId,
        ];
        $state->machineGunHolderId = null;
        $state->currentPlayerIndex = null;
        $state->currentHandIndex = null;
        $state->dealerHoleHidden = false;
        $state->phase = 'round_finished';
    }

    /**
     * @return array{0: BlackjackPlayer, 1: BlackjackHand}
     */
    private function requireCurrentPlayerHand(BlackjackState $state, int $userId): array
    {
        if ($state->currentPlayerIndex === null) {
            throw new BlackjackActionException('No active turn.');
        }

        $player = $state->players[$state->currentPlayerIndex];

        if ($player->userId !== $userId) {
            throw new BlackjackActionException('It is not your turn.', 403);
        }

        $hand = $player->hands[$state->currentHandIndex];

        if ($hand->status !== 'playing') {
            throw new BlackjackActionException('This hand is no longer playable.');
        }

        return [$player, $hand];
    }

    private function applyHit(BlackjackState $state, BlackjackHand $hand): void
    {
        if (! BlackjackRules::canHit($hand)) {
            throw new BlackjackActionException('Hit is not allowed.');
        }

        $hand->addCard($state->deck->draw());

        $score = $hand->score()['value'];

        if ($score > 21) {
            $hand->status = 'bust';
        } elseif ($score === 21) {
            $hand->status = 'stood';
        }
    }

    private function applyStand(BlackjackHand $hand): void
    {
        if (! BlackjackRules::canStand($hand)) {
            throw new BlackjackActionException('Stand is not allowed.');
        }

        $hand->status = 'stood';
    }

    private function applyDouble(BlackjackState $state, BlackjackPlayer $player, BlackjackHand $hand): void
    {
        if (! BlackjackRules::canDouble($hand, $player)) {
            throw new BlackjackActionException('Double is not allowed.');
        }

        $player->chips -= $hand->bet;
        $hand->bet *= 2;
        $hand->addCard($state->deck->draw());

        $hand->status = $hand->isBust() ? 'bust' : 'finished';
    }

    private function applySplit(BlackjackState $state, BlackjackPlayer $player, BlackjackHand $hand): void
    {
        if (! BlackjackRules::canSplit($hand, $player)) {
            throw new BlackjackActionException('Split is not allowed.');
        }

        $isAces = $hand->cards[0]->rank === 'A';

        $player->chips -= $hand->bet;

        $handA = new BlackjackHand([$hand->cards[0]], $hand->bet, 'playing', null, true);
        $handB = new BlackjackHand([$hand->cards[1]], $hand->bet, 'playing', null, true);

        $handA->addCard($state->deck->draw());
        $handB->addCard($state->deck->draw());

        foreach ([$handA, $handB] as $splitHand) {
            if ($splitHand->isBlackjack()) {
                // Ace + ten-value after a split is still Blackjack, whatever
                // the original pair was (see BlackjackHand::isBlackjack()).
                $splitHand->status = 'blackjack';
            } elseif ($isAces) {
                // Split Aces always auto-finish — no further Hit allowed —
                // even when the drawn card isn't a ten-value card.
                $splitHand->status = 'stood';
            }
        }

        $handIndex = $state->currentHandIndex;
        array_splice($player->hands, $handIndex, 1, [$handA, $handB]);
        $state->currentHandIndex = $handIndex;
    }

    private function advanceIfCurrentHandFinished(BlackjackState $state): void
    {
        if ($state->currentPlayerIndex === null) {
            return;
        }

        $player = $state->players[$state->currentPlayerIndex];
        $hand = $player->hands[$state->currentHandIndex];

        if ($hand->status !== 'playing') {
            $this->advanceToNextPlayableHand($state, $state->currentPlayerIndex, $state->currentHandIndex);
        }
    }

    private function advanceToNextPlayableHand(BlackjackState $state, int $fromPlayerIndex, int $fromHandIndex): void
    {
        $playerCount = count($state->players);

        for ($pi = max($fromPlayerIndex, 0); $pi < $playerCount; $pi++) {
            $player = $state->players[$pi];

            if ($player->status !== 'active') {
                continue;
            }

            $startHand = ($pi === $fromPlayerIndex) ? $fromHandIndex + 1 : 0;

            foreach ($player->hands as $hi => $hand) {
                if ($hi < $startHand) {
                    continue;
                }
                if ($hand->status === 'playing') {
                    $state->currentPlayerIndex = $pi;
                    $state->currentHandIndex = $hi;

                    return;
                }
            }
        }

        $state->currentPlayerIndex = null;
        $state->currentHandIndex = null;
        $state->phase = 'dealer_turn';
    }

    private function playDealer(BlackjackState $state): void
    {
        $state->dealerHoleHidden = false;

        $dealerHand = new BlackjackHand($state->dealerCards, 0);

        while ($dealerHand->score()['value'] < BlackjackRules::DEALER_STANDS_AT) {
            $card = $state->deck->draw();
            $dealerHand->addCard($card);
            $state->dealerCards[] = $card;
        }
    }

    private function settle(BlackjackState $state): void
    {
        $dealerHand = new BlackjackHand($state->dealerCards, 0);
        $dealerScore = $dealerHand->score()['value'];
        $dealerBust = $dealerScore > 21;
        $dealerBlackjack = $dealerHand->isBlackjack();

        foreach ($state->players as $player) {
            if ($player->status !== 'active') {
                continue;
            }

            foreach ($player->hands as $hand) {
                $chipsBeforeSettlement = $player->chips;
                $this->settleHand($hand, $player, $dealerScore, $dealerBust, $dealerBlackjack);
                $hand->profit = $player->chips - $chipsBeforeSettlement - $hand->bet;
            }
        }

        $state->phase = 'round_finished';
    }

    private function settleHand(BlackjackHand $hand, BlackjackPlayer $player, int $dealerScore, bool $dealerBust, bool $dealerBlackjack): void
    {
        if ($hand->status === 'bust') {
            $hand->result = 'lose';

            return;
        }

        if ($hand->status === 'blackjack') {
            if ($dealerBlackjack) {
                $hand->result = 'push';
                $player->chips += $hand->bet;
            } else {
                $hand->result = 'blackjack';
                $player->chips += (int) ($hand->bet * 2.5);
            }

            return;
        }

        if ($dealerBlackjack) {
            $hand->result = 'lose';

            return;
        }

        if ($dealerBust) {
            $hand->result = 'win';
            $player->chips += $hand->bet * 2;

            return;
        }

        $handScore = $hand->score()['value'];

        if ($handScore > $dealerScore) {
            $hand->result = 'win';
            $player->chips += $hand->bet * 2;
        } elseif ($handScore === $dealerScore) {
            $hand->result = 'push';
            $player->chips += $hand->bet;
        } else {
            $hand->result = 'lose';
        }
    }

    // --- GameEngine contract adapter -------------------------------------

    public function start(mixed $context = null): mixed
    {
        $context ??= [];

        return $this->deal($context['players'] ?? [], $context['default_bet'] ?? 0, $context['round'] ?? 1);
    }

    public function handleAction(mixed $state, int $userId, string $action, array $payload = []): mixed
    {
        return $this->applyAction($this->castState($state), $userId, $action, $payload);
    }

    public function allowedActions(mixed $state, int $userId): array
    {
        return $this->getAllowedActions($this->castState($state), $userId);
    }

    public function isFinished(mixed $state): bool
    {
        return $this->isRoundFinished($this->castState($state));
    }

    private function castState(mixed $state): BlackjackState
    {
        if (! $state instanceof BlackjackState) {
            throw new \InvalidArgumentException('Expected an instance of BlackjackState.');
        }

        return $state;
    }
}
