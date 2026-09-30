<?php

namespace App\Game\Durak;

final class DurakRules
{
    /**
     * Whether $defense legally beats $attack: same suit and higher rank, or
     * any trump against a non-trump attack.
     */
    public static function beats(Card $defense, Card $attack, string $trumpSuit): bool
    {
        if ($defense->suit === $attack->suit) {
            return $defense->strength() > $attack->strength();
        }

        return $defense->suit === $trumpSuit && $attack->suit !== $trumpSuit;
    }
}
