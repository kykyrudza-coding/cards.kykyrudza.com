<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserStatisticResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $gamesPlayed = $this->gamesPlayed();
        $gamesWon = $this->gamesWon();

        return [
            'overview' => [
                'played' => $gamesPlayed,
                'won' => $gamesWon,
                'losses' => $gamesPlayed - $gamesWon,
                'win_rate' => $gamesPlayed > 0 ? round($gamesWon / $gamesPlayed * 100) : null,
                'current_win_streak' => $this->current_win_streak,
                'best_win_streak' => $this->best_win_streak,
                'peak_match_chips' => $this->peak_match_chips,
            ],
            'blackjack' => [
                'played' => $this->blackjack_hands_played,
                'won' => $this->blackjack_hands_won,
                'losses' => $this->blackjack_hands_played - $this->blackjack_hands_won,
                'win_rate' => $this->blackjack_hands_played > 0
                    ? round($this->blackjack_hands_won / $this->blackjack_hands_played * 100)
                    : null,
                'blackjacks_hit' => $this->blackjacks_hit,
                'splits_performed' => $this->splits_performed,
                'dealer_busts_witnessed' => $this->dealer_busts_witnessed,
            ],
            'durak' => [
                'played' => $this->durak_matches_played,
                'survived' => $this->durak_matches_survived,
                'losses' => $this->durak_losses,
                'win_rate' => $this->durak_matches_played > 0
                    ? round($this->durak_matches_survived / $this->durak_matches_played * 100)
                    : null,
            ],
        ];
    }
}
