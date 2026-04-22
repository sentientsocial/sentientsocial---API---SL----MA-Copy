<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Traits\SendsNotifications;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    use SendsNotifications;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 10);
            /** @var \App\Models\User $currentUser */
            $currentUser = Auth::user();
            
            // Build query with privacy filtering
            $query = Post::with(['user', 'user.profile'])
                ->whereHas('user', function ($q) use ($currentUser) {
                    $q->where(function ($query) use ($currentUser) {
                        // Show posts where:
                        // 1. Author's privacy is 'everyone'
                        $query->where('post_privacy', 'everyone')
                            // 2. OR author's privacy is 'followers' AND current user follows them
                            ->orWhere(function ($q) use ($currentUser) {
                                $q->where('post_privacy', 'followers')
                                  ->whereHas('followers', function ($followQuery) use ($currentUser) {
                                      $followQuery->where('follower_id', $currentUser->id);
                                  });
                            })
                            // 3. OR the post belongs to the current user
                            ->orWhere('id', $currentUser->id);
                    });
                })
                ->orderBy('created_at', 'desc');
            
            $posts = $query->paginate($perPage);

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
                'total' => $posts->total()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching posts: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching posts'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('Post creation request', $request->all());

            $request->validate([
                'title' => 'nullable|string|max:255',
                'content' => 'required|string',
                'type' => 'required|string|in:text,meditation,achievement',
                'location' => 'nullable|string|max:255',
                'feeling' => 'nullable|string|max:100',
                'time' => 'nullable|string|max:100',
                'streak' => 'nullable|string|max:100',
                'avgHR' => 'nullable|string|max:100',
                'media_urls' => 'nullable|array',
                'health_data' => 'nullable|array',
            ]);

            // Build metadata from individual fields
            $metadata = [];
            if ($request->location) $metadata['location'] = $request->location;
            if ($request->feeling) $metadata['feeling'] = $request->feeling;
            if ($request->time) $metadata['time'] = $request->time;
            if ($request->streak) $metadata['streak'] = $request->streak;
            if ($request->avgHR) $metadata['avgHR'] = $request->avgHR;
            if ($request->media_urls) $metadata['media_urls'] = $request->media_urls;

            $post = Post::create([
                'user_id' => Auth::id(),
                'title' => $request->title,
                'content' => $request->content,
                'type' => $request->type,
                'metadata' => !empty($metadata) ? $metadata : null,
                'health_data' => $request->health_data,
                'likes_count' => 0,
                'comments_count' => 0
            ]);

            // Increment posts count for the user
            Auth::user()->profile->incrementPostsCount();

            Log::info('Post created successfully', ['post_id' => $post->id]);

            return response()->json([
                'success' => true,
                'message' => 'Post created successfully',
                'data' => $post->load(['user', 'user.profile'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating post: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error creating post'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post): JsonResponse
    {
        try {
            $post->load(['user', 'user.profile']);
            $metadata = $post->metadata ?? [];
            
            $postData = [
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
            
            return response()->json(['success' => true, 'data' => $postData]);
        } catch (\Exception $e) {
            Log::error('Error fetching post: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching post'], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        try {
            // Check if user owns the post
            if ($post->user_id !== Auth::id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $request->validate([
                'title' => 'nullable|string|max:255',
                'content' => 'required|string',
                'type' => 'required|string|in:text,meditation,achievement',
                'location' => 'nullable|string|max:255',
                'feeling' => 'nullable|string|max:100',
                'time' => 'nullable|string|max:100',
                'streak' => 'nullable|string|max:100',
                'avgHR' => 'nullable|string|max:100',
                'media_urls' => 'nullable|array',
                'health_data' => 'nullable|array',
            ]);

            // Build metadata from individual fields
            $metadata = $post->metadata ?? [];
            if ($request->has('location')) $metadata['location'] = $request->location;
            if ($request->has('feeling')) $metadata['feeling'] = $request->feeling;
            if ($request->has('time')) $metadata['time'] = $request->time;
            if ($request->has('streak')) $metadata['streak'] = $request->streak;
            if ($request->has('avgHR')) $metadata['avgHR'] = $request->avgHR;
            if ($request->has('media_urls')) $metadata['media_urls'] = $request->media_urls;

            $post->update([
                'title' => $request->title,
                'content' => $request->content,
                'type' => $request->type,
                'metadata' => !empty($metadata) ? $metadata : null,
                'health_data' => $request->has('health_data') ? $request->health_data : $post->health_data,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Post updated successfully',
                'data' => $post->load(['user', 'user.profile'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating post: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error updating post'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post): JsonResponse
    {
        try {
            // Check if user owns the post
            if ($post->user_id !== Auth::id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $post->delete();

            // Decrement posts count for the user
            Auth::user()->profile->decrementPostsCount();

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting post: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error deleting post'], 500);
        }
    }

    /**
     * Like a post
     *
     * @param Post $post
     * @return JsonResponse
     */
    public function like(Post $post): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Check if already liked
            if (!$post->isLikedBy($user)) {
                $post->likes()->create(['user_id' => $user->id]);
                $post->increment('likes_count');
                
                // Send like notification
                $this->sendLikeNotification($post->id, $post->user_id);
            }

            return response()->json([
                'success' => true,
                'likes_count' => $post->fresh()->likes_count,
                'is_liked' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Error liking post: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error liking post'], 500);
        }
    }

    /**
     * Unlike a post
     *
     * @param Post $post
     * @return JsonResponse
     */
    public function unlike(Post $post): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Check if liked and remove
            if ($post->isLikedBy($user)) {
                $post->likes()->where('user_id', $user->id)->delete();
                $post->decrement('likes_count');
            }

            return response()->json([
                'success' => true,
                'likes_count' => $post->fresh()->likes_count,
                'is_liked' => false
            ]);
        } catch (\Exception $e) {
            Log::error('Error unliking post: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error unliking post'], 500);
        }
    }

    /**
     * Get users who liked a post
     *
     * @param Post $post
     * @return JsonResponse
     */
    public function getLikes(Post $post): JsonResponse
    {
        try {
            /** @var \Illuminate\Database\Eloquent\Collection $likes */
            $likes = $post->likes()
                ->with(['user', 'user.profile'])
                ->orderBy('created_at', 'desc')
                ->get();

            $likesData = $likes->map(function ($like) {
                return [
                    'id' => $like->user->id,
                    'name' => $like->user->name,
                    'username' => $like->user->profile->username ?? null,
                    'avatar' => $like->user->profile->avatar ?? null,
                    'liked_at' => $like->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $likesData,
                'total' => $likesData->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting post likes: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error getting post likes'], 500);
        }
    }
}
