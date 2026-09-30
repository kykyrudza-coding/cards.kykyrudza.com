<?php

namespace App\Game;

use App\Game\Blackjack\BlackjackEngine;
use App\Game\Blackjack\BlackjackState;
use App\Game\Contracts\GameEngine;
use App\Game\Contracts\GameState;
use App\Game\Durak\DurakEngine;
use App\Game\Durak\DurakState;

/**
 * Single source of truth for which game_type strings are supported and
 * which engine/state classes back them.
 */
final class GameCatalog
{
    private const array GAMES = [
        'blackjack' => ['engine' => BlackjackEngine::class, 'state' => BlackjackState::class],
        'durak' => ['engine' => DurakEngine::class, 'state' => DurakState::class],
    ];

    /**
     * @return string[]
     */
    public static function supportedGameTypes(): array
    {
        return array_keys(self::GAMES);
    }

    public static function engine(string $gameType): GameEngine
    {
        $class = self::GAMES[$gameType]['engine'] ?? throw new \RuntimeException("Unsupported game type: {$gameType}");

        return new $class;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function hydrateState(string $gameType, array $data): GameState
    {
        $class = self::GAMES[$gameType]['state'] ?? throw new \RuntimeException("Unsupported game type: {$gameType}");

        return $class::fromArray($data);
    }
}
