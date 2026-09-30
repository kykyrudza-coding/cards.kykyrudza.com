<?php

namespace App\Game\Durak;

use App\Game\Contracts\GameEngine;

/**
 * Pure Дурак (Durak) rules engine — Подкидной (throw-in) combined with
 * Переводной (translate). Knows nothing about HTTP, the database, Eloquent
 * or broadcasting; only transforms a DurakState given an action, and is
 * fully deterministic given a fixed Deck.
 *
 * Turn shape: an attacker opens with one card; the defender may translate
 * (before defending anything) or must defend each outstanding card; once
 * nothing is outstanding, any active player but the defender may throw in
 * more cards of a rank already on the table, up to attackLimit. Once every
 * eligible player has passed, the table is beaten off ("bito") and the
 * defender becomes the next attacker. If the defender can't/won't cover a
 * card, they take the whole table instead and the turn passes them by.
 */
final class DurakEngine implements GameEngine
{
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

        return new DurakState(
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
        );
    }

    public function applyAction(DurakState $state, int $userId, string $action, array $payload = []): DurakState
    {
        if ($state->phase === 'finished') {
            throw new DurakActionException('The game has already finished.');
        }

        match ($action) {
            'attack' => $this->applyAttack($state, $userId, $payload),
            'translate' => $this->applyTranslate($state, $userId),
            'defend' => $this->applyDefend($state, $userId, $payload),
            'take' => $this->applyTake($state, $userId),
            'pass' => $this->applyPass($state, $userId),
            default => throw new DurakActionException("Unknown action: {$action}"),
        };

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

        $actions = [];
        $isDefender = $playerIndex === $state->defenderIndex;
        $isAttacker = $playerIndex === $state->attackerIndex;
        $outstanding = $this->outstandingCount($state);

        if ($isDefender && $outstanding > 0) {
            $actions[] = 'defend';
            $actions[] = 'take';

            if (count($state->players) >= 3
                && count($state->table) === 1
                && $state->table[0]['defense'] === null
                && $this->canTranslate($state)
            ) {
                $actions[] = 'translate';
            }
        }

        if (! $isDefender && $outstanding === 0 && $state->table !== []
            && in_array($userId, $this->eligibleThrowInUserIds($state), true)
        ) {
            if (count($state->table) < $state->attackLimit && $this->hasThrowInCard($state, $playerIndex)) {
                $actions[] = 'attack';
            }
            if (! in_array($userId, $state->passed, true)) {
                $actions[] = 'pass';
            }
        }

        if ($isAttacker && $state->table === []) {
            $actions[] = 'attack';
        }

        return $actions;
    }

    public function isRoundFinished(DurakState $state): bool
    {
        return $state->phase === 'finished';
    }

    /**
     * Public, per-viewer serialization: only the viewer's own hand is
     * revealed; opponents only expose a hand_count.
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

        return [
            'phase' => $state->phase,
            'round' => $state->round,
            'trump_suit' => $state->trumpSuit,
            'trump_card' => $state->trumpCard->toArray(),
            'deck_count' => $state->deck->remaining(),
            'table' => array_map(fn (array $slot) => [
                'attack' => $slot['attack']->toArray(),
                'defense' => $slot['defense']?->toArray(),
            ], $state->table),
            'players' => $players,
            'attacker_id' => $state->players[$state->attackerIndex]->userId,
            'defender_id' => $state->players[$state->defenderIndex]->userId,
            'allowed_actions' => $this->getAllowedActions($state, $viewerId),
            'loser_id' => $state->loserId,
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

        $defenderId = $state->players[$state->defenderIndex]->userId;
        if ($userId === $defenderId) {
            throw new DurakActionException('The defender cannot attack.', 403);
        }

        $tableEmpty = $state->table === [];

        if ($tableEmpty) {
            $attackerId = $state->players[$state->attackerIndex]->userId;
            if ($userId !== $attackerId) {
                throw new DurakActionException('Only the attacker can open this turn.', 403);
            }
            if (count($cards) !== 1) {
                throw new DurakActionException('The opening attack is a single card.');
            }
        } else {
            if ($this->outstandingCount($state) > 0) {
                throw new DurakActionException('Wait for the defender to respond first.');
            }
            if (! in_array($userId, $this->eligibleThrowInUserIds($state), true)) {
                throw new DurakActionException('You cannot add cards to this table.', 403);
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

        $player = $state->players[$this->requirePlayerIndex($state, $userId)];

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
    }

    /**
     * The defender redirects the sole opening card to the next active
     * player using all of their cards of the same rank — only legal before
     * anything has been defended this turn, and only with 3+ players.
     */
    private function applyTranslate(DurakState $state, int $userId): void
    {
        if (count($state->players) < 3) {
            throw new DurakActionException('Translation needs at least three players.');
        }

        $defender = $state->players[$state->defenderIndex];
        if ($userId !== $defender->userId) {
            throw new DurakActionException('Only the defender can translate.', 403);
        }

        if (count($state->table) !== 1 || $state->table[0]['defense'] !== null) {
            throw new DurakActionException('Translation is only possible on the opening card.');
        }

        $newDefenderIndex = $this->nextActiveIndex($state, $state->defenderIndex);
        if ($newDefenderIndex === $state->attackerIndex) {
            throw new DurakActionException('No one left to translate to.');
        }

        $targetRank = $state->table[0]['attack']->rank;
        $matching = array_values(array_filter($defender->hand, fn (Card $c) => $c->rank === $targetRank));

        if ($matching === []) {
            throw new DurakActionException('You have no card of that rank to translate with.');
        }

        foreach ($matching as $card) {
            $this->removeFromHand($defender, $card);
            $state->table[] = ['attack' => $card, 'defense' => null];
        }

        $state->defenderIndex = $newDefenderIndex;
        $state->attackLimit = max(count($state->table), min(6, count($state->players[$newDefenderIndex]->hand) + count($matching)));
        $state->passed = [];
    }

    private function applyDefend(DurakState $state, int $userId, array $payload): void
    {
        $defender = $state->players[$state->defenderIndex];
        if ($userId !== $defender->userId) {
            throw new DurakActionException('It is not your turn to defend.', 403);
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

        if (! DurakRules::beats($defenseCard, $attackCard, $state->trumpSuit)) {
            throw new DurakActionException('That card does not beat the attack.');
        }

        $this->removeFromHand($defender, $defenseCard);
        $state->table[$slotIndex]['defense'] = $defenseCard;

        $this->maybeResolveBito($state);
    }

    private function applyTake(DurakState $state, int $userId): void
    {
        $defender = $state->players[$state->defenderIndex];
        if ($userId !== $defender->userId) {
            throw new DurakActionException('Only the defender can take.', 403);
        }

        if ($this->outstandingCount($state) === 0) {
            throw new DurakActionException('Everything has already been defended.');
        }

        $this->resolveTake($state);
    }

    private function applyPass(DurakState $state, int $userId): void
    {
        if ($this->outstandingCount($state) > 0) {
            throw new DurakActionException('The defender must respond first.');
        }

        if ($state->table === []) {
            throw new DurakActionException('There is nothing to pass on yet.');
        }

        if (! in_array($userId, $this->eligibleThrowInUserIds($state), true)) {
            throw new DurakActionException('You are not part of this attack.', 403);
        }

        if (! in_array($userId, $state->passed, true)) {
            $state->passed[] = $userId;
        }

        $this->maybeResolveBito($state);
    }

    // --- turn resolution -----------------------------------------------------

    private function maybeResolveBito(DurakState $state): void
    {
        if ($this->outstandingCount($state) === 0 && $this->allPassed($state)) {
            $this->resolveBito($state);
        }
    }

    private function resolveBito(DurakState $state): void
    {
        $oldAttacker = $state->attackerIndex;
        $oldDefender = $state->defenderIndex;

        $state->table = [];
        $state->passed = [];

        $this->refillHands($state, $oldAttacker, $oldDefender, null);

        $state->round++;
        $this->finalizeTurnStart($state, $oldDefender);
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

        $oldAttacker = $state->attackerIndex;
        $state->table = [];
        $state->passed = [];

        $this->refillHands($state, $oldAttacker, $takerIndex, $takerIndex);

        $state->round++;
        $proposedAttacker = $this->nextActiveIndex($state, $takerIndex);
        $this->finalizeTurnStart($state, $proposedAttacker);
    }

    /**
     * Active players draw back up to 6 cards, one card at a time in turn
     * order starting with $startIndex through $endIndex inclusive
     * (wrapping), skipping $excludeIndex (a player who just took, and needs
     * no more cards) — round-robin, not one player filled up before the
     * next, so a nearly-empty deck is shared fairly.
     */
    private function refillHands(DurakState $state, int $startIndex, int $endIndex, ?int $excludeIndex): void
    {
        $order = array_values(array_filter(
            $this->rotationFrom($state, $startIndex, $endIndex),
            fn (int $index) => $index !== $excludeIndex && $state->players[$index]->status === 'active'
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

    /**
     * @return int[]
     */
    private function rotationFrom(DurakState $state, int $startIndex, int $endIndex): array
    {
        $n = count($state->players);
        $order = [];
        $i = $startIndex;

        while (true) {
            $order[] = $i;
            if ($i === $endIndex) {
                break;
            }
            $i = ($i + 1) % $n;
        }

        return $order;
    }

    private function finalizeTurnStart(DurakState $state, int $proposedAttackerIndex): void
    {
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

    private function outstandingCount(DurakState $state): int
    {
        return count(array_filter($state->table, fn (array $slot) => $slot['defense'] === null));
    }

    /**
     * @return int[]
     */
    private function eligibleThrowInUserIds(DurakState $state): array
    {
        $defenderId = $state->players[$state->defenderIndex]->userId;

        return array_values(array_filter(
            array_map(fn (DurakPlayer $p) => $p->userId, array_filter($state->players, fn (DurakPlayer $p) => $p->status === 'active')),
            fn (int $id) => $id !== $defenderId
        ));
    }

    private function allPassed(DurakState $state): bool
    {
        return array_diff($this->eligibleThrowInUserIds($state), $state->passed) === [];
    }

    private function canTranslate(DurakState $state): bool
    {
        $defender = $state->players[$state->defenderIndex];
        $targetRank = $state->table[0]['attack']->rank;

        foreach ($defender->hand as $card) {
            if ($card->rank === $targetRank) {
                return true;
            }
        }

        return false;
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
