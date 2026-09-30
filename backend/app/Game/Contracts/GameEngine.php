<?php

namespace App\Game\Contracts;

interface GameEngine
{
    public function start(mixed $context = null): mixed;

    public function handleAction(
        mixed $state,
        int $userId,
        string $action,
        array $payload = []
    ): mixed;

    public function allowedActions(
        mixed $state,
        int $userId
    ): array;

    public function isFinished(
        mixed $state
    ): bool;

    /**
     * Public, per-viewer serialization: redacts hidden information (e.g. the
     * dealer's hole card, other players' hands) and computes allowed_actions
     * relative to the viewer only.
     *
     * @return array<string, mixed>
     */
    public function publicState(mixed $state, int $viewerId): array;
}
