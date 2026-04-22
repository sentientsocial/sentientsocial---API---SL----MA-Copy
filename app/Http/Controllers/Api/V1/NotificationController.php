<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\UserFcmToken;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Get notification counts by type - PRIORITY endpoint that mobile app needs
     */
    public function counts(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $total = Notification::where('user_id', $user->id)->count();
            $unread = Notification::where('user_id', $user->id)->unread()->count();
            
            $unreadByType = Notification::where('user_id', $user->id)
                ->unread()
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            $counts = [
                'total' => $total,
                'unread' => $unread,
                'message' => $unreadByType[Notification::TYPE_MESSAGE] ?? 0,
                'like' => $unreadByType[Notification::TYPE_LIKE] ?? 0,
                'comment' => $unreadByType[Notification::TYPE_COMMENT] ?? 0,
                'follow' => $unreadByType[Notification::TYPE_FOLLOW] ?? 0,
                'system' => $unreadByType[Notification::TYPE_SYSTEM] ?? 0,
            ];

            return response()->json([
                'success' => true,
                'data' => $counts
            ]);

        } catch (\Exception $e) {
            Log::error('Notification counts error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification counts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get paginated list of notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = min($request->get('per_page', 20), 50); // Max 50 items per page
            $status = $request->get('status'); // 'read', 'unread', or null for all
            $type = $request->get('type'); // Filter by notification type

            $query = Notification::where('user_id', $user->id)
                ->with(['sender:id,name,email'])
                ->latest();

            // Apply status filter
            if ($status === 'read') {
                $query->read();
            } elseif ($status === 'unread') {
                $query->unread();
            }

            // Apply type filter
            if ($type && in_array($type, [
                Notification::TYPE_MESSAGE,
                Notification::TYPE_LIKE,
                Notification::TYPE_COMMENT,
                Notification::TYPE_FOLLOW,
                Notification::TYPE_SYSTEM
            ])) {
                $query->ofType($type);
            }

            $notifications = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $notifications->items(),
                    'pagination' => [
                        'current_page' => $notifications->currentPage(),
                        'per_page' => $notifications->perPage(),
                        'total' => $notifications->total(),
                        'last_page' => $notifications->lastPage(),
                        'has_more' => $notifications->hasMorePages(),
                        'from' => $notifications->firstItem(),
                        'to' => $notifications->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Notification index error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark a specific notification as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $notification = Notification::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => $notification->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Mark notification as read error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $updatedCount = Notification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => "Marked {$updatedCount} notifications as read",
                'data' => [
                    'updated_count' => $updatedCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Mark all notifications as read error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Register or update FCM token for the user
     */
    public function registerFcmToken(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string|min:10',
                'platform' => 'sometimes|string|in:ios,android,web',
                'device_id' => 'sometimes|string|max:255',
                'app_version' => 'sometimes|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid input data',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            
            $fcmToken = UserFcmToken::createOrUpdate(
                $user->id,
                $request->token,
                $request->get('platform', UserFcmToken::PLATFORM_UNKNOWN),
                $request->get('device_id'),
                $request->get('app_version')
            );

            return response()->json([
                'success' => true,
                'message' => 'FCM token registered successfully',
                'data' => [
                    'token_id' => $fcmToken->id,
                    'is_new' => $fcmToken->wasRecentlyCreated
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('FCM token registration error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to register FCM token',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Send a test notification (for debugging purposes)
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        try {
            if (!config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Test notifications only available in debug mode'
                ], 403);
            }

            $user = Auth::user();
            
            // Create a system notification
            $notification = Notification::createSystemNotification(
                $user->id,
                'Test Notification',
                'This is a test notification to verify the system is working correctly.'
            );

            // Try to send push notification
            $result = $this->firebaseService->sendNotificationToUser(
                $user->id,
                $notification->title,
                $notification->message,
                $notification->data
            );

            return response()->json([
                'success' => true,
                'message' => 'Test notification sent',
                'data' => [
                    'notification' => $notification,
                    'push_result' => $result
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Test notification error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Direct Firebase push notification test (bypasses queues)
     */
    public function testDirectFirebase(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get user's FCM tokens
            $fcmTokens = UserFcmToken::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('token')
                ->toArray();
                
            if (empty($fcmTokens)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No FCM tokens found for user',
                    'debug' => [
                        'user_id' => $user->id,
                        'tokens_count' => count($fcmTokens)
                    ]
                ]);
            }
            
            // Create test notification data
            $title = '🔥 Direct Firebase Test';
            $body = 'Direct push notification sent at ' . now()->format('Y-m-d H:i:s');
            $data = [
                'type' => 'direct_test',
                'user_id' => (string) $user->id,
                'timestamp' => (string) time(),
                'test_mode' => 'true'
            ];
            
            Log::info('Direct Firebase test attempt', [
                'user_id' => $user->id,
                'tokens' => $fcmTokens,
                'title' => $title,
                'body' => $body
            ]);
            
            // Send directly via Firebase (no queue)
            $result = $this->firebaseService->sendToTokens(
                $fcmTokens,
                $title,
                $body,
                $data
            );
            
            Log::info('Direct Firebase test result', [
                'result' => $result,
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Direct Firebase push notification sent',
                'data' => [
                    'user_id' => $user->id,
                    'tokens_sent_to' => count($fcmTokens),
                    'fcm_tokens' => $fcmTokens,
                    'firebase_result' => $result,
                    'notification_data' => [
                        'title' => $title,
                        'body' => $body,
                        'data' => $data
                    ],
                    'debug' => [
                        'firebase_configured' => config('services.firebase.project_id') ? true : false,
                        'firebase_credentials_path' => config('services.firebase.credentials'),
                        'credentials_file_exists' => file_exists(storage_path('app/firebase-credentials.json'))
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Direct Firebase test failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Direct Firebase test failed',
                'error' => $e->getMessage(),
                'debug' => [
                    'user_id' => Auth::id(),
                    'firebase_configured' => config('services.firebase.project_id') ? true : false,
                    'firebase_credentials_path' => config('services.firebase.credentials'),
                    'credentials_file_exists' => file_exists(storage_path('app/firebase-credentials.json'))
                ]
            ], 500);
        }
    }
    
    /**
     * Test direct message notification (bypasses queue)
     */
    public function testDirectMessage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'receiver_id' => 'required|exists:users,id',
                'message' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $receiverId = $request->receiver_id;
            $messageContent = $request->message;
            
            // Get receiver's FCM tokens
            $fcmTokens = UserFcmToken::where('user_id', $receiverId)
                ->where('is_active', true)
                ->pluck('token')
                ->toArray();
                
            if (empty($fcmTokens)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No FCM tokens found for receiver',
                    'debug' => [
                        'receiver_id' => $receiverId,
                        'tokens_count' => count($fcmTokens)
                    ]
                ]);
            }
            
            // Create message notification data
            $senderName = $user->name ?? 'Someone';
            $title = 'New Message';
            $body = "{$senderName}: " . substr($messageContent, 0, 100);
            $data = [
                'type' => 'message',
                'sender_id' => (string) $user->id,
                'sender_name' => $senderName,
                'message_preview' => substr($messageContent, 0, 100)
            ];
            
            Log::info('Direct message notification test', [
                'sender_id' => $user->id,
                'receiver_id' => $receiverId,
                'tokens' => $fcmTokens,
                'title' => $title,
                'body' => $body
            ]);
            
            // Send directly via Firebase (no queue)
            $result = $this->firebaseService->sendToTokens(
                $fcmTokens,
                $title,
                $body,
                $data
            );
            
            Log::info('Direct message notification result', [
                'result' => $result,
                'sender_id' => $user->id,
                'receiver_id' => $receiverId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Direct message notification sent',
                'data' => [
                    'sender_id' => $user->id,
                    'receiver_id' => $receiverId,
                    'tokens_sent_to' => count($fcmTokens),
                    'fcm_tokens' => $fcmTokens,
                    'firebase_result' => $result,
                    'notification_data' => [
                        'title' => $title,
                        'body' => $body,
                        'data' => $data
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Direct message notification test failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Direct message notification test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}