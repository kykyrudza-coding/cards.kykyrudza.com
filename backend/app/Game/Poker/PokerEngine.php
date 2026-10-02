<?php

namespace App\Game\Poker;

use App\Game\Contracts\GameEngine;

/**
 * Pure Texas Hold'em rules engine (no-limit, cash-game style: hands keep
 * being dealt until one player holds every chip). Knows nothing about HTTP,
 * the database or broadcasting; it only transforms a PokerState given an
 * action, and is deterministic given a fixed Deck.
 *
 * Betting follows standard no-limit rules: blinds, a minimum raise equal to
 * the previous raise, short all-ins that don't reopen the action, heads-up
 * blinds, side pots and split pots (odd chip to the first seat left of the
 * dealer). A hand ends in 'hand_finished'; every remaining player confirms
 * 'next_hand' before the next one is dealt, so results stay on screen.
 */
final class PokerEngine implements GameEngine
{
    private const array STREETS = ['preflop', 'flop', 'turn', 'river'];

    /**
     * @param  array<int, array{user_id: int, seat: int, chips: int}>  $matchPlayers
     * @param  Deck|null  $deck  Inject a predefined deck for deterministic tests; omit for real play.
     *                           Drawn in order: 2 hole cards per player starting at the small blind
     *                           (two passes), then flop (3), turn, river.
     */
    public function deal(array $matchPlayers, int $bigBlind, ?Deck $deck = null): PokerState
    {
        $players = array_map(
            fn (array $p) => new PokerPlayer($p['user_id'], $p['seat'], $p['chips']),
            $matchPlayers
        );
        usort($players, fn (PokerPlayer $a, PokerPlayer $b) => $a->seat <=> $b->seat);

        if (count($players) < 2) {
            throw new PokerActionException('Poker needs at least two players.');
        }

        $state = new PokerState(
            phase: 'hand_finished',
            deck: Deck::fromCards([]),
            community: [],
            players: $players,
            dealerIndex: count($players) - 1, // so the first hand's button lands on seat 0
            currentIndex: 0,
            currentBet: 0,
            minRaise: $bigBlind,
            bigBlind: $bigBlind,
            round: 0,
        );

        $this->startHand($state, $deck);

        return $state;
    }

    public function applyAction(PokerState $state, int $userId, string $action, array $payload = []): PokerState
    {
        if ($state->phase === 'finished') {
            throw new PokerActionException('The match has already finished.');
        }

        if ($action === 'next_hand') {
            $this->applyNextHand($state, $userId);

            return $state;
        }

        if (! in_array($state->phase, self::STREETS, true)) {
            throw new PokerActionException('The hand is over — wait for the next one.');
        }

        $index = $this->requirePlayerIndex($state, $userId);
        if ($index !== $state->currentIndex) {
            throw new PokerActionException('It is not your turn.', 403);
        }

        $player = $state->players[$index];

        match ($action) {
            'fold' => $this->applyFold($player),
            'check' => $this->applyCheck($state, $player),
            'call' => $this->applyCall($state, $player),
            'raise' => $this->applyRaise($state, $player, $payload),
            'all_in' => $this->applyAllIn($state, $player),
            default => throw new PokerActionException("Unknown action: {$action}"),
        };

        $this->settleBetting($state, $index + 1);

        return $state;
    }

    public function getAllowedActions(PokerState $state, int $userId): array
    {
        $index = $this->findPlayerIndex($state, $userId);
        if ($index === null) {
            return [];
        }
        $player = $state->players[$index];

        if ($state->phase === 'hand_finished') {
            return $player->chips > 0 && ! in_array($userId, $state->ready, true) ? ['next_hand'] : [];
        }

        if (! in_array($state->phase, self::STREETS, true) || $index !== $state->currentIndex) {
            return [];
        }

        $toCall = $state->currentBet - $player->bet;
        $actions = ['fold', $toCall > 0 ? 'call' : 'check'];

        if ($this->canRaise($state, $player)) {
            $actions[] = 'raise';
            $actions[] = 'all_in';
        }

        return $actions;
    }

    public function isRoundFinished(PokerState $state): bool
    {
        return $state->phase === 'finished';
    }

    /**
     * Public, per-viewer serialization: hole cards are only revealed to their
     * owner — or to everyone, for players still in the hand at a showdown.
     *
     * @return array<string, mixed>
     */
    public function publicState(mixed $state, int $viewerId): array
    {
        $state = $this->castState($state);
        $handOver = in_array($state->phase, ['hand_finished', 'finished'], true);
        [$smallBlindIndex, $bigBlindIndex] = $this->blindIndexes($state);
        $viewerIndex = $this->findPlayerIndex($state, $viewerId);

        $players = [];
        foreach ($state->players as $i => $player) {
            $entry = [
                'id' => $player->userId,
                'seat' => $player->seat,
                'status' => $player->status,
                'chips' => $player->chips,
                'bet' => $player->bet,
                'total_bet' => $player->totalBet,
                'hand_count' => count($player->hand),
                'is_dealer' => $i === $state->dealerIndex,
                'is_small_blind' => $i === $smallBlindIndex,
                'is_big_blind' => $i === $bigBlindIndex,
            ];
            $revealed = $state->showdown && $handOver && in_array($player->status, ['active', 'all_in'], true);
            if ($player->userId === $viewerId || $revealed) {
                $entry['hand'] = array_map(fn (Card $c) => $c->toArray(), $player->hand);
            }
            $players[] = $entry;
        }

        $viewer = $viewerIndex !== null ? $state->players[$viewerIndex] : null;
        $toCall = $viewer ? max(0, $state->currentBet - $viewer->bet) : 0;

        return [
            'phase' => $state->phase,
            'round' => $state->round,
            'small_blind' => intdiv($state->bigBlind, 2),
            'big_blind' => $state->bigBlind,
            'community' => array_map(fn (Card $c) => $c->toArray(), $state->community),
            'pot' => array_sum(array_map(fn (PokerPlayer $p) => $p->totalBet, $state->players)),
            'current_bet' => $state->currentBet,
            'to_call' => min($toCall, $viewer?->chips ?? 0),
            'min_raise_to' => $viewer ? min($state->currentBet + $state->minRaise, $viewer->bet + $viewer->chips) : 0,
            'max_raise_to' => $viewer ? $viewer->bet + $viewer->chips : 0,
            'players' => $players,
            'dealer_id' => $state->players[$state->dealerIndex]->userId,
            'current_player_id' => in_array($state->phase, self::STREETS, true)
                ? $state->players[$state->currentIndex]->userId
                : null,
            'allowed_actions' => $this->getAllowedActions($state, $viewerId),
            'showdown' => $state->showdown,
            'results' => $state->results,
            'ready' => $state->ready,
            'winner_id' => $state->winnerId,
        ];
    }

    // --- actions -----------------------------------------------------------

    private function applyFold(PokerPlayer $player): void
    {
        $player->status = 'folded';
        $player->acted = true;
    }

    private function applyCheck(PokerState $state, PokerPlayer $player): void
    {
        if ($player->bet < $state->currentBet) {
            throw new PokerActionException('You cannot check — call, raise or fold.');
        }

        $player->acted = true;
    }

    private function applyCall(PokerState $state, PokerPlayer $player): void
    {
        $toCall = $state->currentBet - $player->bet;
        if ($toCall <= 0) {
            throw new PokerActionException('There is nothing to call — check instead.');
        }

        $this->commit($player, $toCall);
        $player->acted = true;
    }

    private function applyRaise(PokerState $state, PokerPlayer $player, array $payload): void
    {
        $amount = $payload['amount'] ?? null;
        if (! is_int($amount) && ! (is_string($amount) && ctype_digit($amount))) {
            throw new PokerActionException('Enter a whole number of chips to raise to.');
        }
        $amount = (int) $amount;

        if (! $this->canRaise($state, $player)) {
            throw new PokerActionException('You cannot raise here.');
        }

        $maxTo = $player->bet + $player->chips;
        if ($amount > $maxTo) {
            throw new PokerActionException('You do not have that many chips.');
        }
        if ($amount < min($state->currentBet + $state->minRaise, $maxTo)) {
            throw new PokerActionException('The minimum raise is to '.($state->currentBet + $state->minRaise).'.');
        }

        $this->raiseTo($state, $player, $amount);
    }

    private function applyAllIn(PokerState $state, PokerPlayer $player): void
    {
        if (! $this->canRaise($state, $player)) {
            throw new PokerActionException('You cannot raise here.');
        }

        $this->raiseTo($state, $player, $player->bet + $player->chips);
    }

    private function raiseTo(PokerState $state, PokerPlayer $player, int $to): void
    {
        $size = $to - $state->currentBet;
        $this->commit($player, $to - $player->bet);

        if ($to > $state->currentBet) {
            // A short all-in raise still has to be called, but doesn't reopen
            // the betting for players who've already acted.
            if ($size >= $state->minRaise) {
                $state->minRaise = $size;
                foreach ($state->players as $other) {
                    if ($other !== $player && $other->status === 'active') {
                        $other->acted = false;
                    }
                }
            }
            $state->currentBet = $to;
        }

        $player->acted = true;
    }

    private function applyNextHand(PokerState $state, int $userId): void
    {
        if ($state->phase !== 'hand_finished') {
            throw new PokerActionException('The current hand is still in progress.');
        }

        $player = $state->players[$this->requirePlayerIndex($state, $userId)];
        if ($player->chips <= 0) {
            throw new PokerActionException('You are out of chips.', 403);
        }

        if (! in_array($userId, $state->ready, true)) {
            $state->ready[] = $userId;
        }

        $waiting = array_filter(
            $state->players,
            fn (PokerPlayer $p) => $p->chips > 0 && ! in_array($p->userId, $state->ready, true)
        );
        if ($waiting === []) {
            $this->startHand($state);
        }
    }

    // --- hand flow ---------------------------------------------------------

    private function startHand(PokerState $state, ?Deck $deck = null): void
    {
        foreach ($state->players as $player) {
            $player->status = $player->chips > 0 ? 'active' : 'out';
            $player->hand = [];
            $player->bet = 0;
            $player->totalBet = 0;
            $player->acted = false;
        }

        $state->deck = $deck ?? Deck::standard()->shuffle();
        $state->community = [];
        $state->showdown = false;
        $state->results = [];
        $state->ready = [];
        $state->round++;
        $state->dealerIndex = $this->nextIndexWhere($state, $state->dealerIndex + 1, fn (PokerPlayer $p) => $p->status !== 'out');

        $headsUp = count(array_filter($state->players, fn (PokerPlayer $p) => $p->status !== 'out')) === 2;
        $smallBlind = $headsUp
            ? $state->dealerIndex
            : $this->nextIndexWhere($state, $state->dealerIndex + 1, fn (PokerPlayer $p) => $p->status !== 'out');
        $bigBlind = $this->nextIndexWhere($state, $smallBlind + 1, fn (PokerPlayer $p) => $p->status !== 'out');

        $seatCount = count($state->players);
        for ($pass = 0; $pass < 2; $pass++) {
            for ($i = 0; $i < $seatCount; $i++) {
                $player = $state->players[($smallBlind + $i) % $seatCount];
                if ($player->status !== 'out') {
                    $player->hand[] = $state->deck->draw();
                }
            }
        }

        $this->commit($state->players[$smallBlind], intdiv($state->bigBlind, 2));
        $this->commit($state->players[$bigBlind], $state->bigBlind);

        $state->phase = 'preflop';
        $state->currentBet = max(array_map(fn (PokerPlayer $p) => $p->bet, $state->players));
        $state->minRaise = $state->bigBlind;

        $this->settleBetting($state, $headsUp ? $smallBlind : $bigBlind + 1);
    }

    /**
     * After any action (or the blinds): ends the hand if one player is left,
     * hands the turn to whoever still owes an action, or — once the street is
     * settled — moves to the next street / showdown.
     */
    private function settleBetting(PokerState $state, int $startIndex): void
    {
        $inHand = array_filter($state->players, fn (PokerPlayer $p) => in_array($p->status, ['active', 'all_in'], true));
        if (count($inHand) === 1) {
            $this->finishHand($state, false);

            return;
        }

        $next = $this->findNeedingAction($state, $startIndex);
        if ($next !== null) {
            $state->currentIndex = $next;

            return;
        }

        if ($state->phase === 'river' || $this->activeCount($state) <= 1) {
            while (count($state->community) < 5) {
                $state->community[] = $state->deck->draw();
            }
            $this->finishHand($state, true);

            return;
        }

        $this->advanceStreet($state);
    }

    private function advanceStreet(PokerState $state): void
    {
        [$state->phase, $cards] = match ($state->phase) {
            'preflop' => ['flop', 3],
            'flop' => ['turn', 1],
            default => ['river', 1],
        };

        for ($i = 0; $i < $cards; $i++) {
            $state->community[] = $state->deck->draw();
        }

        foreach ($state->players as $player) {
            $player->bet = 0;
            $player->acted = false;
        }
        $state->currentBet = 0;
        $state->minRaise = $state->bigBlind;

        $state->currentIndex = $this->findNeedingAction($state, $state->dealerIndex + 1) ?? $state->dealerIndex;
    }

    private function finishHand(PokerState $state, bool $showdown): void
    {
        $state->showdown = $showdown;
        $state->results = $this->awardPots($state, $showdown);

        foreach ($state->players as $player) {
            $player->bet = 0;
            $player->totalBet = 0;
        }

        $state->phase = 'hand_finished';

        $stacked = array_values(array_filter($state->players, fn (PokerPlayer $p) => $p->chips > 0));
        if (count($stacked) <= 1) {
            $state->phase = 'finished';
            $state->winnerId = $stacked[0]->userId ?? null;
        }
    }

    /**
     * Splits the pot into a main pot and side pots by contribution level and
     * pays each to its best eligible hand(s). A level nobody left in the hand
     * contributed enough to reach is refunded to whoever put it in.
     *
     * @return array<int, array{user_id: int, amount: int, hand: ?string}>
     */
    private function awardPots(PokerState $state, bool $showdown): array
    {
        $levels = array_values(array_unique(array_filter(
            array_map(fn (PokerPlayer $p) => $p->totalBet, $state->players),
            fn (int $bet) => $bet > 0
        )));
        sort($levels);

        $scores = [];
        $payouts = [];
        $handNames = [];
        $previous = 0;

        foreach ($levels as $level) {
            $amount = 0;
            foreach ($state->players as $player) {
                $amount += max(0, min($player->totalBet, $level) - $previous);
            }

            $eligible = array_values(array_filter(
                $state->players,
                fn (PokerPlayer $p) => in_array($p->status, ['active', 'all_in'], true) && $p->totalBet >= $level
            ));

            if ($eligible === []) {
                foreach ($state->players as $player) {
                    $refund = max(0, min($player->totalBet, $level) - $previous);
                    $payouts[$player->userId] = ($payouts[$player->userId] ?? 0) + $refund;
                }
                $previous = $level;

                continue;
            }

            $winners = $eligible;
            if ($showdown && count($eligible) > 1) {
                foreach ($eligible as $player) {
                    $scores[$player->userId] ??= HandEvaluator::best([...$player->hand, ...$state->community]);
                }
                $winners = [];
                $bestScore = null;
                foreach ($eligible as $player) {
                    $score = $scores[$player->userId]['score'];
                    $cmp = $bestScore === null ? 1 : HandEvaluator::compare($score, $bestScore);
                    if ($cmp > 0) {
                        $winners = [$player];
                        $bestScore = $score;
                    } elseif ($cmp === 0) {
                        $winners[] = $player;
                    }
                }
            }

            // The odd chip goes to the winner closest to the left of the dealer.
            $seatCount = count($state->players);
            usort($winners, fn (PokerPlayer $a, PokerPlayer $b) => $this->distanceFromDealer($state, $a, $seatCount) <=> $this->distanceFromDealer($state, $b, $seatCount));
            $share = intdiv($amount, count($winners));
            $remainder = $amount % count($winners);
            foreach ($winners as $i => $winner) {
                $payouts[$winner->userId] = ($payouts[$winner->userId] ?? 0) + $share + ($i < $remainder ? 1 : 0);
                if ($showdown && count($eligible) > 1) {
                    $handNames[$winner->userId] = $scores[$winner->userId]['name'];
                }
            }

            $previous = $level;
        }

        $results = [];
        foreach ($state->players as $player) {
            $won = $payouts[$player->userId] ?? 0;
            if ($won > 0) {
                $player->chips += $won;
                $results[] = ['user_id' => $player->userId, 'amount' => $won, 'hand' => $handNames[$player->userId] ?? null];
            }
        }

        return $results;
    }

    // --- helpers -----------------------------------------------------------

    private function commit(PokerPlayer $player, int $amount): void
    {
        $amount = min($amount, $player->chips);
        $player->chips -= $amount;
        $player->bet += $amount;
        $player->totalBet += $amount;

        if ($player->chips === 0) {
            $player->status = 'all_in';
        }
    }

    private function canRaise(PokerState $state, PokerPlayer $player): bool
    {
        $toCall = $state->currentBet - $player->bet;
        $others = array_filter($state->players, fn (PokerPlayer $p) => $p !== $player && $p->status === 'active');

        return $player->status === 'active'
            && $player->chips > $toCall
            && $others !== []
            && ! ($player->acted && $toCall > 0); // a short all-in didn't reopen the betting
    }

    private function needsAction(PokerState $state, PokerPlayer $player): bool
    {
        if ($player->status !== 'active') {
            return false;
        }

        return $player->bet < $state->currentBet || (! $player->acted && $this->activeCount($state) >= 2);
    }

    private function activeCount(PokerState $state): int
    {
        return count(array_filter($state->players, fn (PokerPlayer $p) => $p->status === 'active'));
    }

    private function findNeedingAction(PokerState $state, int $startIndex): ?int
    {
        $count = count($state->players);
        for ($i = 0; $i < $count; $i++) {
            $index = ($startIndex + $i) % $count;
            if ($this->needsAction($state, $state->players[$index])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  callable(PokerPlayer): bool  $predicate
     */
    private function nextIndexWhere(PokerState $state, int $startIndex, callable $predicate): int
    {
        $count = count($state->players);
        for ($i = 0; $i < $count; $i++) {
            $index = ($startIndex + $i) % $count;
            if ($predicate($state->players[$index])) {
                return $index;
            }
        }

        throw new \LogicException('No matching player.');
    }

    private function distanceFromDealer(PokerState $state, PokerPlayer $player, int $seatCount): int
    {
        $index = array_search($player, $state->players, true);

        return ($index - $state->dealerIndex - 1 + $seatCount) % $seatCount;
    }

    /**
     * @return array{0: ?int, 1: ?int} [small blind index, big blind index] of the current hand.
     */
    private function blindIndexes(PokerState $state): array
    {
        $seated = fn (PokerPlayer $p) => $p->status !== 'out';
        if (count(array_filter($state->players, $seated)) < 2) {
            return [null, null];
        }

        $headsUp = count(array_filter($state->players, $seated)) === 2;
        $small = $headsUp ? $state->dealerIndex : $this->nextIndexWhere($state, $state->dealerIndex + 1, $seated);

        return [$small, $this->nextIndexWhere($state, $small + 1, $seated)];
    }

    private function findPlayerIndex(PokerState $state, int $userId): ?int
    {
        foreach ($state->players as $i => $player) {
            if ($player->userId === $userId) {
                return $i;
            }
        }

        return null;
    }

    private function requirePlayerIndex(PokerState $state, int $userId): int
    {
        return $this->findPlayerIndex($state, $userId)
            ?? throw new PokerActionException('You are not a participant in this match.', 403);
    }

    // --- GameEngine contract adapter -----------------------------------------

    public function start(mixed $context = null): mixed
    {
        $context ??= [];

        return $this->deal($context['players'] ?? [], $context['big_blind'] ?? 100);
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

    private function castState(mixed $state): PokerState
    {
        if (! $state instanceof PokerState) {
            throw new \InvalidArgumentException('Expected an instance of PokerState.');
        }

        return $state;
    }
}
