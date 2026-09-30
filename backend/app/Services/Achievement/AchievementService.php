<?php

namespace App\Services\Achievement;

use App\Game\Blackjack\BlackjackHand;
use App\Game\Blackjack\BlackjackState;
use App\Game\Durak\DurakState;
use App\Models\Achievement;
use App\Models\GameMatch;
use App\Models\User;
use App\Models\UserStatistic;
use Illuminate\Support\Facades\DB;

/**
 * Tracks per-user lifetime stats and unlocks achievements as gameplay
 * events happen. Pure bookkeeping — never throws, never blocks a move;
 * callers fire-and-forget this after a state transition settles.
 */
class AchievementService
{
    /**
     * Call after every Blackjack action, regardless of what it was — this
     * only does anything the moment a round newly settles (transitions
     * into 'round_finished'), crediting every player's every hand that
     * round (a split creates two independently-scored hands).
     */
    public function afterBlackjackAction(GameMatch $match, string $previousPhase, BlackjackState $state): void
    {
        if ($previousPhase === 'round_finished' || $state->phase !== 'round_finished') {
            return;
        }

        $dealerBust = (new BlackjackHand($state->dealerCards, 0))->score()['value'] > 21;

        foreach ($state->players as $player) {
            $user = User::find($player->userId);
            if (! $user || $player->hands === []) {
                continue;
            }

            $stat = $this->statFor($user);

            foreach ($player->hands as $hand) {
                $won = match ($hand->result) {
                    'win', 'blackjack' => true,
                    'push', null => null,
                    default => false,
                };

                $this->credit($stat, [
                    'blackjack_hands_played' => 1,
                    'blackjack_hands_won' => $won === true ? 1 : 0,
                    'blackjacks_hit' => $hand->status === 'blackjack' ? 1 : 0,
                    'high_roller_wins' => $won === true && $hand->bet >= 1000 ? 1 : 0,
                    'dealer_busts_witnessed' => $won === true && $dealerBust ? 1 : 0,
                ], $won);
            }

            $stat->peak_match_chips = max($stat->peak_match_chips, $player->chips);
            $stat->save();

            $this->checkUnlocks($user, $stat);
        }
    }

    public function recordSplit(User $user): void
    {
        $stat = $this->statFor($user);
        $stat->splits_performed++;
        $stat->save();

        $this->checkUnlocks($user, $stat);
    }

    /**
     * Call after every Durak action — only acts the moment the match
     * transitions into 'finished'.
     */
    public function afterDurakAction(GameMatch $match, string $previousPhase, DurakState $state): void
    {
        if ($previousPhase === 'finished' || $state->phase !== 'finished') {
            return;
        }

        foreach ($state->players as $player) {
            $user = User::find($player->userId);
            if (! $user) {
                continue;
            }

            $stat = $this->statFor($user);
            $survived = $state->loserId !== $player->userId;

            $this->credit($stat, [
                'durak_matches_played' => 1,
                'durak_matches_survived' => $survived ? 1 : 0,
                'durak_losses' => $survived ? 0 : 1,
            ], $survived);

            $stat->save();

            $this->checkUnlocks($user, $stat);
        }
    }

    private function statFor(User $user): UserStatistic
    {
        return UserStatistic::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * @param  array<string, int>  $increments
     */
    private function credit(UserStatistic $stat, array $increments, ?bool $won): void
    {
        foreach ($increments as $column => $delta) {
            $stat->{$column} = ($stat->{$column} ?? 0) + $delta;
        }

        if ($won === true) {
            $stat->current_win_streak++;
            $stat->best_win_streak = max($stat->best_win_streak, $stat->current_win_streak);
        } elseif ($won === false) {
            $stat->current_win_streak = 0;
        }
    }

    private function checkUnlocks(User $user, UserStatistic $stat): void
    {
        $conditions = [
            'first_win' => $stat->blackjack_hands_won >= 1 || $stat->durak_matches_survived >= 1,
            'natural_blackjack' => $stat->blackjacks_hit >= 1,
            'high_roller' => $stat->high_roller_wins >= 1,
            'win_streak_3' => $stat->best_win_streak >= 3,
            'split_master' => $stat->splits_performed >= 5,
            'dealer_buster' => $stat->dealer_busts_witnessed >= 10,
            'durak_veteran' => $stat->durak_matches_survived >= 5,
            'marathoner' => $stat->gamesPlayed() >= 25,
            'chip_fortune' => $stat->peak_match_chips >= 10000,
            'two_games' => $stat->blackjack_hands_played >= 1 && $stat->durak_matches_played >= 1,
        ];

        foreach ($conditions as $key => $met) {
            if ($met) {
                $this->unlock($user, $key);
            }
        }
    }

    private function unlock(User $user, string $key): void
    {
        $achievement = Achievement::where('key', $key)->first();
        if (! $achievement) {
            return;
        }

        // insertOrIgnore + the unique(user_id, achievement_id) constraint
        // makes this race-safe and idempotent — repeated qualifying rounds
        // never re-unlock, duplicate a row, or throw on a concurrent match.
        DB::table('user_achievements')->insertOrIgnore([
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
            'unlocked_at' => now(),
        ]);
    }
}
