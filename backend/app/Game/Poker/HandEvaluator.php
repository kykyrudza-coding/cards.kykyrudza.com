<?php

namespace App\Game\Poker;

/**
 * Ranks the best five-card poker hand out of any five-or-more cards.
 * A hand is scored as an int[] — [category, ...tiebreakers] — compared
 * lexicographically via compare().
 */
final class HandEvaluator
{
    private const array NAMES = [
        0 => 'high_card',
        1 => 'pair',
        2 => 'two_pair',
        3 => 'three_of_a_kind',
        4 => 'straight',
        5 => 'flush',
        6 => 'full_house',
        7 => 'four_of_a_kind',
        8 => 'straight_flush',
    ];

    /**
     * @param  Card[]  $cards  five to seven cards
     * @return array{score: int[], name: string}
     */
    public static function best(array $cards): array
    {
        $cards = array_values($cards);
        $count = count($cards);

        if ($count < 5) {
            throw new \InvalidArgumentException('A poker hand needs at least five cards.');
        }

        $best = null;

        for ($a = 0; $a < $count - 4; $a++) {
            for ($b = $a + 1; $b < $count - 3; $b++) {
                for ($c = $b + 1; $c < $count - 2; $c++) {
                    for ($d = $c + 1; $d < $count - 1; $d++) {
                        for ($e = $d + 1; $e < $count; $e++) {
                            $score = self::score([$cards[$a], $cards[$b], $cards[$c], $cards[$d], $cards[$e]]);
                            if ($best === null || self::compare($score, $best) > 0) {
                                $best = $score;
                            }
                        }
                    }
                }
            }
        }

        return ['score' => $best, 'name' => self::NAMES[$best[0]]];
    }

    /**
     * @param  int[]  $a
     * @param  int[]  $b
     */
    public static function compare(array $a, array $b): int
    {
        $length = max(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $diff = ($a[$i] ?? 0) <=> ($b[$i] ?? 0);
            if ($diff !== 0) {
                return $diff;
            }
        }

        return 0;
    }

    /**
     * @param  Card[]  $cards  exactly five
     * @return int[]
     */
    private static function score(array $cards): array
    {
        $values = array_map(fn (Card $c) => $c->value(), $cards);
        rsort($values);

        $isFlush = count(array_unique(array_map(fn (Card $c) => $c->suit, $cards))) === 1;
        $straightHigh = self::straightHigh($values);

        // [count, value] groups, biggest group first, then highest value.
        $groups = [];
        foreach (array_count_values($values) as $value => $n) {
            $groups[] = [$n, $value];
        }
        usort($groups, fn (array $x, array $y) => [$y[0], $y[1]] <=> [$x[0], $x[1]]);
        $shape = array_map(fn (array $g) => $g[0], $groups);
        $ordered = array_map(fn (array $g) => $g[1], $groups);

        if ($isFlush && $straightHigh) {
            return [8, $straightHigh];
        }
        if ($shape[0] === 4) {
            return [7, ...$ordered];
        }
        if ($shape === [3, 2]) {
            return [6, ...$ordered];
        }
        if ($isFlush) {
            return [5, ...$values];
        }
        if ($straightHigh) {
            return [4, $straightHigh];
        }
        if ($shape[0] === 3) {
            return [3, ...$ordered];
        }
        if ($shape === [2, 2, 1]) {
            return [2, ...$ordered];
        }
        if ($shape[0] === 2) {
            return [1, ...$ordered];
        }

        return [0, ...$values];
    }

    /**
     * @param  int[]  $sortedDesc
     * @return int Top card of the straight (5 for the A-2-3-4-5 wheel), or 0 if none.
     */
    private static function straightHigh(array $sortedDesc): int
    {
        if (count(array_unique($sortedDesc)) !== 5) {
            return 0;
        }
        if ($sortedDesc[0] - $sortedDesc[4] === 4) {
            return $sortedDesc[0];
        }

        return $sortedDesc === [14, 5, 4, 3, 2] ? 5 : 0;
    }
}
