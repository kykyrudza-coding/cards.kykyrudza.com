<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AchievementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'icon' => $this->icon,
            'unlocked_at' => $this->whenPivotLoaded('user_achievements', fn () => $this->pivot->unlocked_at),
        ];
    }
}
