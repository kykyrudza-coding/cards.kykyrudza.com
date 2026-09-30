<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'blackjack_hands_played',
    'blackjack_hands_won',
    'blackjacks_hit',
    'high_roller_wins',
    'dealer_busts_witnessed',
    'splits_performed',
    'durak_matches_played',
    'durak_matches_survived',
    'durak_losses',
    'current_win_streak',
    'best_win_streak',
    'peak_match_chips',
])]
class UserStatistic extends Model
{
    // So StatisticsController can build a zero-value response via
    // firstOrNew() for a user who hasn't played yet, without writing a row.
    protected $attributes = [
        'blackjack_hands_played' => 0,
        'blackjack_hands_won' => 0,
        'blackjacks_hit' => 0,
        'high_roller_wins' => 0,
        'dealer_busts_witnessed' => 0,
        'splits_performed' => 0,
        'durak_matches_played' => 0,
        'durak_matches_survived' => 0,
        'durak_losses' => 0,
        'current_win_streak' => 0,
        'best_win_streak' => 0,
        'peak_match_chips' => 0,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gamesPlayed(): int
    {
        return $this->blackjack_hands_played + $this->durak_matches_played;
    }

    public function gamesWon(): int
    {
        return $this->blackjack_hands_won + $this->durak_matches_survived;
    }
}
