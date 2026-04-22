<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Comment;
use App\Traits\SendsNotifications;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    use SendsNotifications;

    /**
     * Display a listing of comments for a post.
     */
    public function index(Post $post): JsonResponse
    {
        try {
            $comments = $post->comments()
                ->with(['user', 'user.profile'])
                ->latest()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $comments->items(),
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'total' => $comments->total()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching comments: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching comments'], 500);
        }
    }

    /**
     * Store a newly created comment.
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        try {
            $request->validate([
                'content' => 'required|string|max:1000',
            ]);

            $comment = $post->comments()->create([
                'user_id' => $request->user()->id,
                'content' => $request->content,
            ]);

            // Load user relationship for response
            $comment->load(['user', 'user.profile']);
            
            // Send comment notification
            $this->sendCommentNotification($post->id, $post->user_id, $request->content);

            Log::info('Comment created', ['comment_id' => $comment->id, 'post_id' => $post->id]);

            return response()->json([
                'success' => true,
                'message' => 'Comment created successfully',
                'data' => $comment
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating comment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error creating comment'], 500);
        }
    }

    /**
     * Display the specified comment.
     */
    public function show(Comment $comment): JsonResponse
    {
        try {
            $comment->load(['user', 'user.profile', 'post']);
            
            return response()->json([
                'success' => true,
                'data' => $comment
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching comment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }
    }

    /**
     * Update the specified comment.
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        try {
            // Check if user owns the comment
            if ($comment->user_id !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $request->validate([
                'content' => 'required|string|max:1000',
            ]);

            $comment->update([
                'content' => $request->content,
            ]);

            $comment->load(['user', 'user.profile']);

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'data' => $comment
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating comment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error updating comment'], 500);
        }
    }

    /**
     * Remove the specified comment.
     */
    public function destroy(Comment $comment, Request $request): JsonResponse
    {
        try {
            // Check if user owns the comment
            if ($comment->user_id !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting comment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error deleting comment'], 500);
        }
    }
}
