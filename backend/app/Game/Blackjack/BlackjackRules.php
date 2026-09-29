<?php

namespace App\Game\Blackjack;

final class BlackjackRules
{
    public const int MAX_HANDS_PER_PLAYER = 2;

    public const int DEALER_STANDS_AT = 17;

    public static function canHit(BlackjackHand $hand): bool
    {
        return $hand->status === 'playing';
    }

    public static function canStand(BlackjackHand $hand): bool
    {
        return $hand->status === 'playing';
    }

    public static function canDouble(BlackjackHand $hand, BlackjackPlayer $player): bool
    {
        return $hand->status === 'playing'
            && count($hand->cards) === 2
            && $player->chips >= $hand->bet;
    }

    public static function canSplit(BlackjackHand $hand, BlackjackPlayer $player): bool
    {
        return $hand->status === 'playing'
            && count($hand->cards) === 2
            && $hand->cards[0]->baseValue() === $hand->cards[1]->baseValue()
            && count($player->hands) < self::MAX_HANDS_PER_PLAYER
            && $player->chips >= $hand->bet;
    }

    /**
     * @return string[]
     */
    public static function allowedActions(BlackjackHand $hand, BlackjackPlayer $player): array
    {
        $actions = [];

        if (self::canHit($hand)) {
            $actions[] = 'hit';
        }

        if (self::canStand($hand)) {
            $actions[] = 'stand';
        }

        if (self::canDouble($hand, $player)) {
            $actions[] = 'double';
        }

        if (self::canSplit($hand, $player)) {
            $actions[] = 'split';
        }

        return $actions;
    }
}
