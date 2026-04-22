<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $user->load('profile');
        
        return new UserResource($user);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'username' => [
                'sometimes',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('profiles', 'username')->ignore($user->profile?->id)
            ],
            'display_name' => 'sometimes|string|max:100',
            'bio' => 'sometimes|string|max:500',
            'phone' => 'sometimes|string|max:20|regex:/^[\+]?[0-9\-\(\)\s]+$/',
            'date_of_birth' => 'sometimes|date|before:today',
            'meditation_goals' => 'sometimes|array',
            'weekly_meditation_volume' => 'sometimes|integer|min:1|max:168', // max hours in a week
            'meditation_minutes' => 'sometimes|integer|min:0|max:100000', // total meditation minutes
            'streak_count' => 'sometimes|integer|min:0',
            'avatar_url' => 'sometimes|url',
            'location' => 'sometimes|string|max:100',
            'website' => 'sometimes|url',
            'is_private' => 'sometimes|boolean',
        ]);

        // Update or create profile
        $profile = $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => new UserResource($user->load('profile'))
        ]);
    }
}
