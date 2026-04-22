<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'avatar' => $this->avatar,
            'background_image' => $this->background_image,
            'meditation_minutes' => $this->meditation_minutes,
            'streak_count' => $this->streak_count,
            'last_meditation_at' => $this->last_meditation_at,
            'meditation_goals' => $this->meditation_goals,
            'weekly_meditation_volume' => $this->weekly_meditation_volume,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
