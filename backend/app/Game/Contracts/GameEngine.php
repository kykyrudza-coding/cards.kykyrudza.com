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
}
