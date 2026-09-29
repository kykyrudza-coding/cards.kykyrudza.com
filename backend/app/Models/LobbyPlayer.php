<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LobbyPlayer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'lobby_id',
        'user_id',
        'seat',
        'is_ready',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'is_ready' => 'boolean',
            'joined_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
