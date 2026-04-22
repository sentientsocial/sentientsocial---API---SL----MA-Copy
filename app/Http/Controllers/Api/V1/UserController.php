<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\SendsNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    use SendsNotifications;

    /**
     * Get current authenticated user
     */
    public function me()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $user->load('profile');

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load('profile');

        // Check if current user is following this user
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        $user->is_following = $currentUser ? $currentUser->following()->where('following_id', $user->id)->exists() : false;

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Follow a user
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function follow(User $user)
    {
        try {
            /** @var User $currentUser */
            $currentUser = Auth::user();

            // Check if user is trying to follow themselves
            if ($currentUser->id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot follow yourself'
                ], 400);
            }

            // Check if already following
            /** @var bool $isFollowing */
            $isFollowing = $currentUser->following()->where('following_id', $user->id)->exists();
            if ($isFollowing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already following this user'
                ], 400);
            }

            // Create follow relationship using attach method
            $currentUser->following()->attach($user->id);
            
            // Send follow notification
            $this->sendFollowNotification($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Successfully followed user'
            ]);

        } catch (\Exception $e) {
            Log::error('Follow error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to follow user'
            ], 500);
        }
    }

        /**
     * Unfollow a user
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function unfollow(User $user)
    {
        try {
            /** @var User $currentUser */
            $currentUser = Auth::user();

            // Check if not following
            /** @var bool $isFollowing */
            $isFollowing = $currentUser->following()->where('following_id', $user->id)->exists();
            if (!$isFollowing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not following this user'
                ], 400);
            }

            // Remove follow relationship
            $currentUser->following()->detach($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Successfully unfollowed user'
            ]);

        } catch (\Exception $e) {
            Log::error('Unfollow error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to unfollow user'
            ], 500);
        }
    }

    /**
     * Get user's followers
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function followers(User $user)
    {
        /** @var \Illuminate\Database\Eloquent\Collection $followers */
        $followers = $user->followers()->with('profile')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $followers
        ]);
    }

    /**
     * Get users that the user is following
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function following(User $user)
    {
        /** @var \Illuminate\Database\Eloquent\Collection $following */
        $following = $user->following()->with('profile')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $following
        ]);
    }

    /**
     * Search for users
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        // Validate request
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $query = trim($request->get('q'));
        $limit = $request->get('limit', 50);
        
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        
        if (!$currentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            // Start building the query
            $users = User::with(['profile'])
                ->where('id', '!=', $currentUser->id) // Exclude current user
                ->where(function ($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                      ->orWhere('email', 'LIKE', "%{$query}%")
                      ->orWhereHas('profile', function ($profileQuery) use ($query) {
                          $profileQuery->where('username', 'LIKE', "%{$query}%")
                                      ->orWhere('display_name', 'LIKE', "%{$query}%")
                                      ->orWhere('bio', 'LIKE', "%{$query}%");
                      });
                })
                ->limit($limit)
                ->get();

            // Transform results and add is_following status
            $results = $users->map(function ($user) use ($currentUser) {
                $isFollowing = $currentUser->following()->where('following_id', $user->id)->exists();
                
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile' => [
                        'username' => $user->profile->username ?? null,
                        'display_name' => $user->profile->display_name ?? $user->name,
                        'bio' => $user->profile->bio ?? null,
                        'avatar' => $user->profile->avatar ?? null,
                        'background_image' => $user->profile->background_image ?? null,
                        'meditation_minutes' => $user->profile->meditation_minutes ?? 0,
                        'streak_count' => $user->profile->streak_count ?? 0,
                        'last_meditation_at' => $user->profile->last_meditation_at ?? null
                    ],
                    'posts_count' => $user->posts_count,
                    'followers_count' => $user->followers_count,
                    'following_count' => $user->following_count,
                    'is_following' => $isFollowing,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ];
            });

            // Log search query for analytics (without personal data)
            Log::info('User search performed', [
                'query_length' => strlen($query),
                'results_count' => $results->count(),
                'user_id' => $currentUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => 'Search completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('User search failed', [
                'error' => $e->getMessage(),
                'user_id' => $currentUser->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Search failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Get user's posts
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function posts(User $user)
    {
        $perPage = request('per_page', 10);
        /** @var User $currentUser */
        $currentUser = Auth::user();
        
        // Start building the query
        $query = $user->posts()->with(['user', 'user.profile']);
        
        // Apply privacy filtering if not viewing own posts
        if ($currentUser->id !== $user->id) {
            // Check the author's privacy settings
            if ($user->post_privacy === 'only_me') {
                // If privacy is 'only_me', return empty result
                $query->whereRaw('1 = 0'); // This will return no results
            } elseif ($user->post_privacy === 'followers') {
                // If privacy is 'followers', check if current user follows this user
                $isFollowing = $currentUser->following()->where('following_id', $user->id)->exists();
                if (!$isFollowing) {
                    // Not following, return empty result
                    $query->whereRaw('1 = 0');
                }
            }
            // If privacy is 'everyone', no filtering needed
        }
        
        $posts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $posts->getCollection()->transform(function ($post) {
            $metadata = $post->metadata ?? [];
            return [
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->content,
                'type' => $post->type,
                'location' => $metadata['location'] ?? null,
                'feeling' => $metadata['feeling'] ?? null,
                'time' => $metadata['time'] ?? null,
                'streak' => $metadata['streak'] ?? null,
                'avgHR' => $metadata['avgHR'] ?? null,
                'media_urls' => $metadata['media_urls'] ?? null,
                'health_data' => $post->health_data,
                'likes_count' => $post->likes_count,
                'comments_count' => $post->comments_count,
                'is_liked' => $post->isLikedBy(Auth::user()),
                'user_id' => $post->user->id,
                'user_name' => $post->user->name,
                'user_avatar' => $post->user->profile->avatar ?? null,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $posts->items(),
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'per_page' => $posts->perPage(),
            'total' => $posts->total(),
        ]);
    }

    /**
     * Update user's privacy settings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePrivacySettings(Request $request)
    {
        try {
            $request->validate([
                'post_privacy' => 'required|string|in:only_me,followers,everyone'
            ]);

            /** @var User $currentUser */
            $currentUser = Auth::user();
            
            $currentUser->post_privacy = $request->post_privacy;
            $currentUser->save();

            return response()->json([
                'success' => true,
                'message' => 'Privacy settings updated successfully',
                'data' => [
                    'post_privacy' => $currentUser->post_privacy
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Privacy settings update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update privacy settings'
            ], 500);
        }
    }

    /**
     * Get user's privacy settings
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPrivacySettings(User $user)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'post_privacy' => $user->post_privacy ?? 'everyone'
            ]
        ]);
    }
}
