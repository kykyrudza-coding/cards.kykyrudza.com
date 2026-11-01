<?php

namespace App\Game\Durak;

use App\Game\Contracts\GameEngine;
use Illuminate\Support\Carbon;

/**
 * Pure Дурак (Durak) rules engine — Подкидной (throw-in) with Переводной
 * (translate). Knows nothing about HTTP, the database, Eloquent or
 * broadcasting; it only transforms a DurakState given an action.
 *
 * The turn, as played at a real table:
 *  - Players sit in a circle. The attacker opens with a card (or several of
 *    one rank); the defender is the next active player clockwise.
 *  - Only the defender's two neighbours — the attacker and the player after
 *    the defender — may add cards ("ближние подкидывают"), always of a rank
 *    already on the table, and never more than min(6, the defender's hand
 *    when the turn began).
 *  - The defender beats each card, or says "take". After "take" the
 *    neighbours may still throw in, then the defender picks everything up
 *    and loses their turn to attack.
 *  - While nothing has been beaten the defender may translate: add card(s) of
 *    the same rank and pass the whole table to the next active player — who
 *    may translate again, all the way round, even back to whoever opened —
 *    as long as the new defender holds enough cards to answer.
 *  - Once everything is beaten and nobody can or wants to add more, the table
 *    goes to the discard ("бито"), hands are refilled (attacker first, the
 *    other neighbours clockwise, the defender last) and the defender attacks.
 *  - Every action restarts a clock; when it runs out anyone may call
 *    `timeout`, which plays the safest move for whoever was holding things up.
 */
final class DurakEngine implements GameEngine
{
    public const int TURN_SECONDS = 60;

    private const int LOG_LIMIT = 14;

    /** @var \Closure(): int */
    private \Closure $clock;

    /**
     * @param  \Closure(): int|null  $clock  Unix-time source; inject a fake one in tests.
     */
    public function __construct(?\Closure $clock = null)
    {
        // Carbon (not time()) so Laravel's travel()/freezeTime() helpers drive the clock in tests.
        $this->clock = $clock ?? fn (): int => Carbon::now()->getTimestamp();
    }

    /**
     * @param  array<int, array{user_id: int, seat: int}>  $matchPlayers
     * @param  Deck|null  $deck  Inject a predefined deck for deterministic tests; omit for real play.
     */
    public function deal(array $matchPlayers, ?Deck $deck = null): DurakState
    {
        $players = array_map(
            fn (array $p) => new DurakPlayer($p['user_id'], $p['seat']),
            $matchPlayers
        );
        usort($players, fn (DurakPlayer $a, DurakPlayer $b) => $a->seat <=> $b->seat);

        $deck ??= Deck::standard()->shuffle();

        for ($i = 0; $i < 6; $i++) {
            foreach ($players as $player) {
                $player->hand[] = $deck->draw();
            }
        }

        $trumpCard = $deck->draw();
        $deck->putOnBottom($trumpCard);

        $attackerIndex = $this->pickFirstAttacker($players, $trumpCard->suit);
        $defenderIndex = ($attackerIndex + 1) % count($players);

        $state = new DurakState(
            phase: 'attack',
            deck: $deck,
            trumpSuit: $trumpCard->suit,
            trumpCard: $trumpCard,
            players: $players,
            table: [],
            attackerIndex: $attackerIndex,
            defenderIndex: $defenderIndex,
            attackLimit: min(6, count($players[$defenderIndex]->hand)),
            round: 1,
            turnSeconds: self::TURN_SECONDS,
        );
        $this->restartClock($state);

        return $state;
    }

    public function applyAction(DurakState $state, int $userId, string $action, array $payload = []): DurakState
    {
        if ($state->phase === 'finished') {
            throw new DurakActionException('The game has already finished.');
        }

        match ($action) {
            'attack' => $this->applyAttack($state, $userId, $payload),
            'translate' => $this->applyTranslate($state, $userId, $payload),
            'defend' => $this->applyDefend($state, $userId, $payload),
            'take' => $this->applyTake($state, $userId),
            'pass' => $this->applyPass($state, $userId),
            'timeout' => $this->applyTimeout($state, $userId),
            default => throw new DurakActionException("Unknown action: {$action}"),
        };

        $this->restartClock($state);

        return $state;
    }

    public function getAllowedActions(DurakState $state, int $userId): array
    {
        if ($state->phase === 'finished') {
            return [];
        }

        $playerIndex = $this->findPlayerIndex($state, $userId);

        if ($playerIndex === null || $state->players[$playerIndex]->status !== 'active') {
            return [];
        }

        if ($playerIndex === $state->defenderIndex) {
            if ($state->taking || $this->outstandingCount($state) === 0) {
                return [];
            }

            $actions = ['defend', 'take'];
            if ($this->canTranslate($state)) {
                $actions[] = 'translate';
            }

            return $actions;
        }

        if ($state->table === []) {
            return $playerIndex === $state->attackerIndex ? ['attack'] : [];
        }

        if (! in_array($playerIndex, $this->throwerIndexes($state), true)) {
            return [];
        }

        $actions = [];
        if ($this->canThrowIn($state, $playerIndex)) {
            $actions[] = 'attack';
        }
        if (! in_array($userId, $state->passed, true)) {
            $actions[] = 'pass';
        }

        return $actions;
    }

    public function isRoundFinished(DurakState $state): bool
    {
        return $state->phase === 'finished';
    }

    /**
     * Public, per-viewer serialization: only the viewer's own hand is
     * revealed; opponents only expose a hand_count. Also carries hints for the
     * viewer (which cards are playable, what beats what) so the client never
     * has to re-implement the rules.
     *
     * @return array<string, mixed>
     */
    public function publicState(mixed $state, int $viewerId): array
    {
        $state = $this->castState($state);

        $players = [];
        foreach ($state->players as $player) {
            $entry = [
                'id' => $player->userId,
                'seat' => $player->seat,
                'status' => $player->status,
                'hand_count' => count($player->hand),
            ];
            if ($player->userId === $viewerId) {
                $entry['hand'] = array_map(fn (Card $c) => $c->toArray(), $player->hand);
            }
            $players[] = $entry;
        }

        $finished = $state->phase === 'finished';
        $throwers = $finished ? [] : array_map(
            fn (int $i) => $state->players[$i]->userId,
            $this->throwerIndexes($state)
        );

        return [
            'phase' => $state->phase,
            'round' => $state->round,
            'trump_suit' => $state->trumpSuit,
            'trump_card' => $state->trumpCard->toArray(),
            'deck_count' => $state->deck->remaining(),
            'discard_count' => $state->discardCount,
            'table' => array_map(fn (array $slot) => [
                'attack' => $slot['attack']->toArray(),
                'defense' => $slot['defense']?->toArray(),
            ], $state->table),
            'players' => $players,
            'attacker_id' => $state->players[$state->attackerIndex]->userId,
            'defender_id' => $state->players[$state->defenderIndex]->userId,
            'throwers' => $throwers,
            'passed' => $state->passed,
            'taking' => $state->taking,
            'attack_limit' => $state->attackLimit,
            'allowed_actions' => $this->getAllowedActions($state, $viewerId),
            'hints' => $this->hintsFor($state, $viewerId),
            'log' => $state->log,
            'loser_id' => $state->loserId,
            'turn_deadline' => $state->turnDeadline,
            'turn_seconds' => $state->turnSeconds,
            'server_time' => $this->now(),
        ];
    }

    // --- actions -----------------------------------------------------------

    private function applyAttack(DurakState $state, int $userId, array $payload): void
    {
        $cards = $this->cardsFromPayload($payload);

        if ($cards === []) {
            throw new DurakActionException('Select at least one card.');
        }

        $rankSuits = array_map(fn (Card $c) => $c->rank.'|'.$c->suit, $cards);
        if (count($rankSuits) !== count(array_unique($rankSuits))) {
            throw new DurakActionException('Duplicate card in selection.');
        }

        $playerIndex = $this->requirePlayerIndex($state, $userId);

        if ($playerIndex === $state->defenderIndex) {
            throw new DurakActionException('The defender cannot attack.', 403);
        }

        if ($state->table === []) {
            if ($playerIndex !== $state->attackerIndex) {
                throw new DurakActionException('Only the attacker can open this turn.', 403);
            }
            if (count(array_unique(array_map(fn (Card $c) => $c->rank, $cards))) !== 1) {
                throw new DurakActionException('The opening attack is cards of a single rank.');
            }
        } else {
            if (! in_array($playerIndex, $this->throwerIndexes($state), true)) {
                throw new DurakActionException('Only the players next to the defender can add cards.', 403);
            }
            $ranksOnTable = $this->ranksOnTable($state);
            foreach ($cards as $card) {
                if (! in_array($card->rank, $ranksOnTable, true)) {
                    throw new DurakActionException('Only ranks already on the table can be thrown in.');
                }
            }
        }

        if (count($state->table) + count($cards) > $state->attackLimit) {
            throw new DurakActionException("That would exceed this turn's card limit.");
        }

        $player = $state->players[$playerIndex];

        foreach ($cards as $card) {
            if (! $this->inHand($player, $card)) {
                throw new DurakActionException('You do not hold that card.');
            }
        }

        foreach ($cards as $card) {
            $this->removeFromHand($player, $card);
            $state->table[] = ['attack' => $card, 'defense' => null];
        }

        $state->passed = [];
        $state->phase = 'attack';
        $this->record($state, $userId, 'attack', $cards);
        $this->maybeResolve($state);
    }

    /**
     * The defender redirects the table to the next active player by adding
     * card(s) of the table's rank — only while nothing has been beaten, and
     * only if the new defender holds enough cards to answer the whole table.
     * It can be repeated down the line, all the way back to whoever opened.
     *
     * @param  array{cards?: array<int, array{rank: string, suit: string}>}  $payload
     */
    private function applyTranslate(DurakState $state, int $userId, array $payload): void
    {
        $defender = $state->players[$state->defenderIndex];
        if ($userId !== $defender->userId) {
            throw new DurakActionException('Only the defender can translate.', 403);
        }

        if ($state->taking) {
            throw new DurakActionException('You already decided to take the cards.');
        }

        if ($this->activeCount($state) < 3) {
            throw new DurakActionException('Translation needs at least three players still in the game.');
        }

        if (! $this->tableIsTranslatable($state)) {
            throw new DurakActionException('Translation is only possible before anything has been beaten.');
        }

        $targetRank = $state->table[0]['attack']->rank;
        $matching = array_values(array_filter($defender->hand, fn (Card $c) => $c->rank === $targetRank));

        $chosen = $this->cardsFromPayload($payload);
        $cards = $chosen === [] ? $matching : $chosen;

        if ($cards === []) {
            throw new DurakActionException('You have no card of that rank to translate with.');
        }

        $rankSuits = array_map(fn (Card $c) => $c->rank.'|'.$c->suit, $cards);
        if (count($rankSuits) !== count(array_unique($rankSuits))) {
            throw new DurakActionException('Duplicate card in selection.');
        }

        foreach ($cards as $card) {
            if ($card->rank !== $targetRank) {
                throw new DurakActionException('Translate only with cards of the same rank as the attack.');
            }
            if (! $this->inHand($defender, $card)) {
                throw new DurakActionException('You do not hold that card.');
            }
        }

        $newDefenderIndex = $this->nextActiveIndex($state, $state->defenderIndex);
        $newDefender = $state->players[$newDefenderIndex];

        if (count($state->table) + count($cards) > count($newDefender->hand)) {
            throw new DurakActionException('The next player does not have enough cards to answer.');
        }

        foreach ($cards as $card) {
            $this->removeFromHand($defender, $card);
            $state->table[] = ['attack' => $card, 'defense' => null];
        }

        $state->attackerIndex = $state->defenderIndex;
        $state->defenderIndex = $newDefenderIndex;
        $state->attackLimit = min(6, count($newDefender->hand));
        $state->passed = [];
        $this->record($state, $userId, 'translate', $cards);
    }

    private function applyDefend(DurakState $state, int $userId, array $payload): void
    {
        $defender = $state->players[$state->defenderIndex];
        if ($userId !== $defender->userId) {
            throw new DurakActionException('It is not your turn to defend.', 403);
        }

        if ($state->taking) {
            throw new DurakActionException('You already decided to take the cards.');
        }

        $attackCard = $this->cardFromPayload($payload, 'attack');
        $defenseCard = $this->cardFromPayload($payload, 'defense');

        $slotIndex = null;
        foreach ($state->table as $i => $slot) {
            if ($slot['defense'] === null
                && $slot['attack']->rank === $attackCard->rank
                && $slot['attack']->suit === $attackCard->suit
            ) {
                $slotIndex = $i;
                break;
            }
        }

        if ($slotIndex === null) {
            throw new DurakActionException('That card is not awaiting a defense.');
        }

        if (! $this->inHand($defender, $defenseCard)) {
            throw new DurakActionException('You do not hold that card.');
        }

        if (! DurakRules::beats($defenseCard, $state->table[$slotIndex]['attack'], $state->trumpSuit)) {
            throw new DurakActionException('That card does not beat the attack.');
        }

        $this->removeFromHand($defender, $defenseCard);
        $state->table[$slotIndex]['defense'] = $defenseCard;
        $this->record($state, $userId, 'defend', [$defenseCard]);
        $this->maybeResolve($state);
    }

    private function applyTake(DurakState $state, int $userId): void
    {
        $defender = $state->players[$state->defenderIndex];
        if ($userId !== $defender->userId) {
            throw new DurakActionException('Only the defender can take.', 403);
        }

        if ($state->taking) {
            throw new DurakActionException('You already decided to take the cards.');
        }

        if ($this->outstandingCount($state) === 0) {
            throw new DurakActionException('Everything has already been defended.');
        }

        $state->taking = true;
        $this->record($state, $userId, 'take');
        $this->maybeResolve($state);
    }

    private function applyPass(DurakState $state, int $userId): void
    {
        if ($state->table === []) {
            throw new DurakActionException('There is nothing to pass on yet.');
        }

        $playerIndex = $this->requirePlayerIndex($state, $userId);
        if (! in_array($playerIndex, $this->throwerIndexes($state), true)) {
            throw new DurakActionException('You are not next to the defender.', 403);
        }

        if (! in_array($userId, $state->passed, true)) {
            $state->passed[] = $userId;
            $this->record($state, $userId, 'pass');
        }

        $this->maybeResolve($state);
    }

    /**
     * Anyone at the table may call this once the clock has run out. It makes
     * whoever was holding up the game do the obvious thing: the attacker opens
     * with their weakest card, a defender with outstanding cards takes, and
     * neighbours who haven't spoken simply pass.
     */
    private function applyTimeout(DurakState $state, int $userId): void
    {
        $this->requirePlayerIndex($state, $userId);

        if ($state->turnDeadline === null || $this->now() < $state->turnDeadline) {
            throw new DurakActionException('The turn clock has not run out yet.');
        }

        if (! $state->taking && $state->table === []) {
            $attacker = $state->players[$state->attackerIndex];
            $card = $this->weakestCard($attacker, $state->trumpSuit);
            $this->removeFromHand($attacker, $card);
            $state->table[] = ['attack' => $card, 'defense' => null];
            $this->record($state, $attacker->userId, 'attack', [$card], auto: true);

            return;
        }

        if (! $state->taking && $this->outstandingCount($state) > 0) {
            $state->taking = true;
            $this->record($state, $state->players[$state->defenderIndex]->userId, 'take', auto: true);
            $this->maybeResolve($state);

            return;
        }

        foreach ($this->throwerIndexes($state) as $index) {
            $thrower = $state->players[$index];
            if (! in_array($thrower->userId, $state->passed, true)) {
                $state->passed[] = $thrower->userId;
                $this->record($state, $thrower->userId, 'pass', auto: true);
            }
        }
        $this->maybeResolve($state);
    }

    // --- turn resolution -----------------------------------------------------

    /**
     * The turn ends by itself as soon as every player who could still add a
     * card has either passed or has nothing legal to add.
     */
    private function maybeResolve(DurakState $state): void
    {
        if ($state->taking) {
            if ($this->allSettled($state)) {
                $this->resolveTake($state);
            }

            return;
        }

        if ($state->table !== [] && $this->outstandingCount($state) === 0 && $this->allSettled($state)) {
            $this->resolveBito($state);
        }
    }

    private function resolveBito(DurakState $state): void
    {
        $attacker = $state->attackerIndex;
        $defender = $state->defenderIndex;

        $state->discardCount += count($state->table) * 2;
        $state->table = [];
        $state->passed = [];
        $this->record($state, $state->players[$defender]->userId, 'bito');

        $this->refillHands($state, $attacker, $defender, defenderTakes: false);

        $state->round++;
        $this->finalizeTurnStart($state, $defender);
    }

    private function resolveTake(DurakState $state): void
    {
        $takerIndex = $state->defenderIndex;
        $taker = $state->players[$takerIndex];

        foreach ($state->table as $slot) {
            $taker->hand[] = $slot['attack'];
            if ($slot['defense'] !== null) {
                $taker->hand[] = $slot['defense'];
            }
        }

        $attacker = $state->attackerIndex;
        $state->table = [];
        $state->passed = [];

        $this->refillHands($state, $attacker, $takerIndex, defenderTakes: true);

        $state->round++;
        $this->finalizeTurnStart($state, $this->nextActiveIndex($state, $takerIndex));
    }

    /**
     * Everyone still in draws back up to six, one card at a time: the
     * attacker first, then the rest clockwise starting after the defender,
     * the defender last (and not at all after taking).
     */
    private function refillHands(DurakState $state, int $attackerIndex, int $defenderIndex, bool $defenderTakes): void
    {
        $n = count($state->players);
        $order = [$attackerIndex];
        for ($i = ($defenderIndex + 1) % $n; $i !== $attackerIndex; $i = ($i + 1) % $n) {
            $order[] = $i;
        }
        if (! $defenderTakes) {
            $order[] = $defenderIndex;
        }

        $order = array_values(array_filter(
            array_unique($order),
            fn (int $index) => $state->players[$index]->status === 'active'
        ));

        $needsAnotherPass = true;
        while ($needsAnotherPass && $state->deck->remaining() > 0) {
            $needsAnotherPass = false;
            foreach ($order as $index) {
                if ($state->deck->remaining() === 0) {
                    break;
                }
                $player = $state->players[$index];
                if (count($player->hand) < 6) {
                    $player->hand[] = $state->deck->draw();
                    if (count($player->hand) < 6) {
                        $needsAnotherPass = true;
                    }
                }
            }
        }
    }

    private function finalizeTurnStart(DurakState $state, int $proposedAttackerIndex): void
    {
        $state->taking = false;
        $this->updateSafeStatuses($state);

        if ($this->tryFinish($state)) {
            return;
        }

        $attackerIndex = $state->players[$proposedAttackerIndex]->status === 'active'
            ? $proposedAttackerIndex
            : $this->nextActiveIndex($state, $proposedAttackerIndex);
        $defenderIndex = $this->nextActiveIndex($state, $attackerIndex);

        $state->attackerIndex = $attackerIndex;
        $state->defenderIndex = $defenderIndex;
        $state->attackLimit = min(6, count($state->players[$defenderIndex]->hand));
        $state->phase = 'attack';
    }

    private function updateSafeStatuses(DurakState $state): void
    {
        if ($state->deck->remaining() > 0) {
            return;
        }

        foreach ($state->players as $player) {
            if ($player->status === 'active' && count($player->hand) === 0) {
                $player->status = 'safe';
            }
        }
    }

    private function tryFinish(DurakState $state): bool
    {
        $active = array_values(array_filter($state->players, fn (DurakPlayer $p) => $p->status === 'active'));

        if (count($active) > 1) {
            return false;
        }

        $state->phase = 'finished';
        $state->loserId = count($active) === 1 ? $active[0]->userId : null;
        $state->turnDeadline = null;

        return true;
    }

    // --- helpers -------------------------------------------------------------

    private function pickFirstAttacker(array $players, string $trumpSuit): int
    {
        $attackerIndex = null;
        $lowestStrength = null;

        foreach ($players as $i => $player) {
            foreach ($player->hand as $card) {
                if ($card->suit !== $trumpSuit) {
                    continue;
                }
                if ($lowestStrength === null || $card->strength() < $lowestStrength) {
                    $lowestStrength = $card->strength();
                    $attackerIndex = $i;
                }
            }
        }

        return $attackerIndex ?? random_int(0, count($players) - 1);
    }

    private function nextActiveIndex(DurakState $state, int $fromIndex): int
    {
        $n = count($state->players);

        for ($step = 1; $step <= $n; $step++) {
            $candidate = ($fromIndex + $step) % $n;
            if ($state->players[$candidate]->status === 'active') {
                return $candidate;
            }
        }

        throw new DurakActionException('No active players remain.');
    }

    private function previousActiveIndex(DurakState $state, int $fromIndex): int
    {
        $n = count($state->players);

        for ($step = 1; $step <= $n; $step++) {
            $candidate = (($fromIndex - $step) % $n + $n) % $n;
            if ($state->players[$candidate]->status === 'active') {
                return $candidate;
            }
        }

        throw new DurakActionException('No active players remain.');
    }

    private function activeCount(DurakState $state): int
    {
        return count(array_filter($state->players, fn (DurakPlayer $p) => $p->status === 'active'));
    }

    private function outstandingCount(DurakState $state): int
    {
        return count(array_filter($state->table, fn (array $slot) => $slot['defense'] === null));
    }

    /**
     * The players allowed to add cards: the two next to the defender (the one
     * who attacked and the one after the defender). Heads-up that is one.
     *
     * @return int[]
     */
    private function throwerIndexes(DurakState $state): array
    {
        $defender = $state->defenderIndex;

        return array_values(array_unique(array_filter([
            $this->previousActiveIndex($state, $defender),
            $this->nextActiveIndex($state, $defender),
        ], fn (int $index) => $index !== $defender)));
    }

    private function canThrowIn(DurakState $state, int $playerIndex): bool
    {
        return count($state->table) < $state->attackLimit && $this->hasThrowInCard($state, $playerIndex);
    }

    private function allSettled(DurakState $state): bool
    {
        foreach ($this->throwerIndexes($state) as $index) {
            if (in_array($state->players[$index]->userId, $state->passed, true)) {
                continue;
            }
            if ($this->canThrowIn($state, $index)) {
                return false;
            }
        }

        return true;
    }

    private function tableIsTranslatable(DurakState $state): bool
    {
        if ($state->table === []) {
            return false;
        }

        $rank = $state->table[0]['attack']->rank;
        foreach ($state->table as $slot) {
            if ($slot['defense'] !== null || $slot['attack']->rank !== $rank) {
                return false;
            }
        }

        return true;
    }

    private function canTranslate(DurakState $state): bool
    {
        if ($state->taking || $this->activeCount($state) < 3 || ! $this->tableIsTranslatable($state)) {
            return false;
        }

        $defender = $state->players[$state->defenderIndex];
        $rank = $state->table[0]['attack']->rank;
        $holds = array_filter($defender->hand, fn (Card $c) => $c->rank === $rank);
        if ($holds === []) {
            return false;
        }

        $next = $state->players[$this->nextActiveIndex($state, $state->defenderIndex)];

        return count($next->hand) >= count($state->table) + 1;
    }

    private function hasThrowInCard(DurakState $state, int $playerIndex): bool
    {
        $ranks = $this->ranksOnTable($state);

        foreach ($state->players[$playerIndex]->hand as $card) {
            if (in_array($card->rank, $ranks, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function ranksOnTable(DurakState $state): array
    {
        $ranks = [];
        foreach ($state->table as $slot) {
            $ranks[] = $slot['attack']->rank;
            if ($slot['defense'] !== null) {
                $ranks[] = $slot['defense']->rank;
            }
        }

        return array_values(array_unique($ranks));
    }

    private function weakestCard(DurakPlayer $player, string $trumpSuit): Card
    {
        $hand = $player->hand;
        usort($hand, function (Card $a, Card $b) use ($trumpSuit) {
            $trumpA = $a->suit === $trumpSuit ? 1 : 0;
            $trumpB = $b->suit === $trumpSuit ? 1 : 0;

            return [$trumpA, $a->strength()] <=> [$trumpB, $b->strength()];
        });

        return $hand[0];
    }

    /**
     * What the viewer can do with their cards right now.
     *
     * @return array{playable: array<int, array<string, string>>, defenses: array<int, array<string, mixed>>, translate: array<int, array<string, string>>}
     */
    private function hintsFor(DurakState $state, int $viewerId): array
    {
        $hints = ['playable' => [], 'defenses' => [], 'translate' => []];
        $index = $this->findPlayerIndex($state, $viewerId);
        if ($index === null || $state->phase === 'finished') {
            return $hints;
        }

        $player = $state->players[$index];
        $allowed = $this->getAllowedActions($state, $viewerId);

        if (in_array('attack', $allowed, true)) {
            $ranks = $state->table === [] ? null : $this->ranksOnTable($state);
            foreach ($player->hand as $card) {
                if ($ranks === null || in_array($card->rank, $ranks, true)) {
                    $hints['playable'][] = $card->toArray();
                }
            }
        }

        if (in_array('defend', $allowed, true)) {
            foreach ($state->table as $slot) {
                if ($slot['defense'] !== null) {
                    continue;
                }
                $with = array_values(array_filter(
                    $player->hand,
                    fn (Card $c) => DurakRules::beats($c, $slot['attack'], $state->trumpSuit)
                ));
                $hints['defenses'][] = [
                    'attack' => $slot['attack']->toArray(),
                    'with' => array_map(fn (Card $c) => $c->toArray(), $with),
                ];
            }
        }

        if (in_array('translate', $allowed, true)) {
            $rank = $state->table[0]['attack']->rank;
            foreach ($player->hand as $card) {
                if ($card->rank === $rank) {
                    $hints['translate'][] = $card->toArray();
                }
            }
        }

        return $hints;
    }

    /**
     * @param  Card[]  $cards
     */
    private function record(DurakState $state, int $userId, string $action, array $cards = [], bool $auto = false): void
    {
        $state->log[] = [
            'user_id' => $userId,
            'action' => $action,
            'cards' => array_map(fn (Card $c) => $c->toArray(), $cards),
            'auto' => $auto,
            'round' => $state->round,
        ];

        if (count($state->log) > self::LOG_LIMIT) {
            $state->log = array_slice($state->log, -self::LOG_LIMIT);
        }
    }

    private function restartClock(DurakState $state): void
    {
        $state->turnDeadline = $state->phase === 'finished' || $state->turnSeconds <= 0
            ? null
            : $this->now() + $state->turnSeconds;
    }

    private function now(): int
    {
        return ($this->clock)();
    }

    private function findPlayerIndex(DurakState $state, int $userId): ?int
    {
        foreach ($state->players as $i => $player) {
            if ($player->userId === $userId) {
                return $i;
            }
        }

        return null;
    }

    private function requirePlayerIndex(DurakState $state, int $userId): int
    {
        return $this->findPlayerIndex($state, $userId)
            ?? throw new DurakActionException('You are not a participant in this match.', 403);
    }

    private function inHand(DurakPlayer $player, Card $card): bool
    {
        foreach ($player->hand as $c) {
            if ($c->rank === $card->rank && $c->suit === $card->suit) {
                return true;
            }
        }

        return false;
    }

    private function removeFromHand(DurakPlayer $player, Card $card): void
    {
        foreach ($player->hand as $i => $c) {
            if ($c->rank === $card->rank && $c->suit === $card->suit) {
                array_splice($player->hand, $i, 1);

                return;
            }
        }

        throw new DurakActionException('You do not hold that card.');
    }

    /**
     * @return Card[]
     */
    private function cardsFromPayload(array $payload): array
    {
        $cards = $payload['cards'] ?? [];

        return array_map(fn (array $c) => new Card($c['rank'], $c['suit']), $cards);
    }

    private function cardFromPayload(array $payload, string $key): Card
    {
        if (! isset($payload[$key]['rank'], $payload[$key]['suit'])) {
            throw new DurakActionException("Missing {$key} card.");
        }

        return new Card($payload[$key]['rank'], $payload[$key]['suit']);
    }

    // --- GameEngine contract adapter -----------------------------------------

    public function start(mixed $context = null): mixed
    {
        $context ??= [];

        return $this->deal($context['players'] ?? []);
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

    private function castState(mixed $state): DurakState
    {
        if (! $state instanceof DurakState) {
            throw new \InvalidArgumentException('Expected an instance of DurakState.');
        }

        return $state;
    }
}
