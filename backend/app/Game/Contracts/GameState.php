<?php

namespace App\Game\Contracts;

/**
 * Marker for a game's serializable round state (what's stored in
 * matches.state). Each concrete state class also exposes a matching
 * static fromArray(array $data): self, invoked via GameCatalog rather
 * than through this interface (PHP can't express that contract cleanly).
 */
interface GameState
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
