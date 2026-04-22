<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Traits\SendsNotifications;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    use SendsNotifications;

    /**
     * Get user's messages (General and Request tabs)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $type = $request->get('type', 'general'); // 'general' or 'request'
            $perPage = $request->get('per_page', 20);

            $query = Message::where('receiver_id', $user->id)
                          ->with(['sender', 'sender.profile'])
                          ->orderBy('created_at', 'desc');

            if ($type === 'general') {
                $query->where('type', 'general');
            } elseif ($type === 'request') {
                $query->where('type', 'request');
            }

            $messages = $query->paginate($perPage);

            $messages->getCollection()->transform(function ($message) {
                return [
                    'id' => $message->id,
                    'sender' => [
                        'id' => $message->sender->id,
                        'name' => $message->sender->name,
                        'avatar' => $message->sender->profile->avatar ?? null,
                        'username' => $message->sender->profile->username ?? null,
                    ],
                    'content' => $message->content,
                    'is_read' => $message->is_read,
                    'type' => $message->type,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $messages->items(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch messages'
            ], 500);
        }
    }

    /**
     * Send a message to another user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'receiver_id' => 'required|exists:users,id',
                'content' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $sender = Auth::user();
            $receiverId = $request->receiver_id;

            // Check if user is trying to message themselves
            if ($sender->id === $receiverId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot send messages to yourself'
                ], 400);
            }

            // Determine message type based on following relationship
            $type = Message::determineType($sender->id, $receiverId);

            $message = Message::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiverId,
                'content' => $request->content,
                'type' => $type,
            ]);
            
            // Send message notification
            $this->sendMessageNotification($receiverId, $request->content);

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $message->load(['sender', 'sender.profile'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message'
            ], 500);
        }
    }

    /**
     * Get conversation with a specific user
     *
     * @param User $user
     * @return JsonResponse
     */
    public function conversation(User $user): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            $messages = Message::where(function ($query) use ($currentUser, $user) {
                $query->where('sender_id', $currentUser->id)
                      ->where('receiver_id', $user->id);
            })->orWhere(function ($query) use ($currentUser, $user) {
                $query->where('sender_id', $user->id)
                      ->where('receiver_id', $currentUser->id);
            })
            ->with(['sender', 'sender.profile', 'receiver', 'receiver.profile'])
            ->orderBy('created_at', 'asc')
            ->get();

            // Mark messages from the other user as read
            Message::where('sender_id', $user->id)
                  ->where('receiver_id', $currentUser->id)
                  ->where('is_read', false)
                  ->update(['is_read' => true]);

            $messages->transform(function ($message) {
                return [
                    'id' => $message->id,
                    'sender' => [
                        'id' => $message->sender->id,
                        'name' => $message->sender->name,
                        'avatar' => $message->sender->profile->avatar ?? null,
                    ],
                    'receiver' => [
                        'id' => $message->receiver->id,
                        'name' => $message->receiver->name,
                        'avatar' => $message->receiver->profile->avatar ?? null,
                    ],
                    'content' => $message->content,
                    'is_read' => $message->is_read,
                    'type' => $message->type,
                    'created_at' => $message->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $messages,
                'total' => $messages->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch conversation'
            ], 500);
        }
    }

    /**
     * Mark message as read
     *
     * @param Message $message
     * @return JsonResponse
     */
    public function markAsRead(Message $message): JsonResponse
    {
        try {
            $user = Auth::user();

            // Ensure user can only mark their own received messages as read
            if ($message->receiver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $message->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Message marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark message as read'
            ], 500);
        }
    }

    /**
     * Get unread message count
     *
     * @return JsonResponse
     */
    public function unreadCount(): JsonResponse
    {
        try {
            $user = Auth::user();

            $generalCount = Message::where('receiver_id', $user->id)
                                 ->where('type', 'general')
                                 ->where('is_read', false)
                                 ->count();

            $requestCount = Message::where('receiver_id', $user->id)
                                 ->where('type', 'request')
                                 ->where('is_read', false)
                                 ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'general' => $generalCount,
                    'request' => $requestCount,
                    'total' => $generalCount + $requestCount,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count'
            ], 500);
        }
    }

    /**
     * Delete a message
     *
     * @param Message $message
     * @return JsonResponse
     */
    public function destroy(Message $message): JsonResponse
    {
        try {
            $user = Auth::user();

            // Ensure user can only delete their own messages
            if ($message->sender_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $message->delete();

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message'
            ], 500);
        }
    }
}
