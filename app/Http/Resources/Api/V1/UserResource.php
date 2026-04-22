<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'profile' => [
                'username' => $this->profile->username,
                'display_name' => $this->profile->display_name,
                'bio' => $this->profile->bio,
                'avatar' => $this->profile->avatar,
                'background_image' => $this->profile->background_image,
                'meditation_minutes' => $this->profile->meditation_minutes,
                'streak_count' => $this->profile->streak_count,
                'last_meditation_at' => $this->profile->last_meditation_at,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
