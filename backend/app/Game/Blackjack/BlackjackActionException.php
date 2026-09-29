<?php

namespace App\Game\Blackjack;

class BlackjackActionException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
