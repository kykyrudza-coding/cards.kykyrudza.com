<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'lobby_id',
        'game_type',
        'status',
        'round_number',
        'state',
        'version',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'round_number' => 'integer',
            'version' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lobby, $this>
     */
    public function lobby(): BelongsTo
    {
        return $this->belongsTo(Lobby::class);
    }

    /**
     * @return HasMany<MatchPlayer, $this>
     */
    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }
}
