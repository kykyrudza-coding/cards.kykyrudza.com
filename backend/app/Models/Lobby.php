<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Hidden(['password'])]
class Lobby extends Model
{
    protected $fillable = [
        'code',
        'host_id',
        'game_type',
        'status',
        'max_players',
        'starting_chips',
        'default_bet',
        'is_private',
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'max_players' => 'integer',
            'starting_chips' => 'integer',
            'default_bet' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * @return HasMany<LobbyPlayer, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(LobbyPlayer::class);
    }

    /**
     * @return HasMany<GameMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    /**
     * @return HasOne<GameMatch, $this>
     */
    public function activeMatch(): HasOne
    {
        return $this->hasOne(GameMatch::class)->where('status', 'active')->latestOfMany();
    }
}
