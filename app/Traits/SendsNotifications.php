<?php

namespace App\Traits;

use App\Jobs\SendNotification;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

trait SendsNotifications
{
    /**
     * Create and send a like notification
     */
    protected function sendLikeNotification(int $postId, int $postOwnerId): void
    {
        try {
            $user = Auth::user();
            
            // Don't send notification if user is liking their own post
            if ($user->id === $postOwnerId) {
                return;
            }

            // Create notification in database
            $notification = Notification::createLikeNotification(
                $postOwnerId,
                $user->id,
                $postId
            );

            // Queue push notification
            $senderName = $user->name ?? 'Someone';
            SendNotification::dispatch(
                $postOwnerId,
                'Post Liked',
                "{$senderName} liked your post",
                [
                    'type' => 'like',
                    'post_id' => $postId,
                    'sender_id' => $user->id,
                    'sender_name' => $senderName
                ],
                $notification->id
            );

            Log::info("Like notification sent to user {$postOwnerId} for post {$postId}");
            
        } catch (\Exception $e) {
            Log::error("Failed to send like notification: " . $e->getMessage());
        }
    }

    /**
     * Create and send a comment notification
     */
    protected function sendCommentNotification(int $postId, int $postOwnerId, string $commentContent): void
    {
        try {
            $user = Auth::user();
            
            // Don't send notification if user is commenting on their own post
            if ($user->id === $postOwnerId) {
                return;
            }

            // Create notification in database
            $notification = Notification::createCommentNotification(
                $postOwnerId,
                $user->id,
                $postId,
                $commentContent
            );

            // Queue push notification
            $senderName = $user->name ?? 'Someone';
            $truncatedComment = substr($commentContent, 0, 50) . (strlen($commentContent) > 50 ? '...' : '');
            
            SendNotification::dispatch(
                $postOwnerId,
                'New Comment',
                "{$senderName} commented: {$truncatedComment}",
                [
                    'type' => 'comment',
                    'post_id' => $postId,
                    'sender_id' => $user->id,
                    'sender_name' => $senderName,
                    'comment_preview' => $truncatedComment
                ],
                $notification->id
            );

            Log::info("Comment notification sent to user {$postOwnerId} for post {$postId}");
            
        } catch (\Exception $e) {
            Log::error("Failed to send comment notification: " . $e->getMessage());
        }
    }

    /**
     * Create and send a follow notification
     */
    protected function sendFollowNotification(int $followedUserId): void
    {
        try {
            $user = Auth::user();

            // Create notification in database
            $notification = Notification::createFollowNotification(
                $followedUserId,
                $user->id
            );

            // Queue push notification
            $senderName = $user->name ?? 'Someone';
            SendNotification::dispatch(
                $followedUserId,
                'New Follower',
                "{$senderName} started following you",
                [
                    'type' => 'follow',
                    'sender_id' => $user->id,
                    'sender_name' => $senderName
                ],
                $notification->id
            );

            Log::info("Follow notification sent to user {$followedUserId}");
            
        } catch (\Exception $e) {
            Log::error("Failed to send follow notification: " . $e->getMessage());
        }
    }

    /**
     * Create and send a message notification
     */
    protected function sendMessageNotification(int $recipientId, string $messageContent): void
    {
        try {
            $user = Auth::user();

            // Create notification in database
            $notification = Notification::createMessageNotification(
                $recipientId,
                $user->id,
                $messageContent
            );

            // Queue push notification
            $senderName = $user->name ?? 'Someone';
            $truncatedMessage = substr($messageContent, 0, 100) . (strlen($messageContent) > 100 ? '...' : '');
            
            SendNotification::dispatch(
                $recipientId,
                'New Message',
                "{$senderName}: {$truncatedMessage}",
                [
                    'type' => 'message',
                    'sender_id' => $user->id,
                    'sender_name' => $senderName,
                    'message_preview' => $truncatedMessage
                ],
                $notification->id
            );

            Log::info("Message notification sent to user {$recipientId}");
            
        } catch (\Exception $e) {
            Log::error("Failed to send message notification: " . $e->getMessage());
        }
    }

    /**
     * Create and send a system notification
     */
    protected function sendSystemNotification(
        int $userId, 
        string $title, 
        string $message, 
        array $additionalData = []
    ): void {
        try {
            // Create notification in database
            $notification = Notification::createSystemNotification(
                $userId,
                $title,
                $message,
                $additionalData
            );

            // Queue push notification
            SendNotification::dispatch(
                $userId,
                $title,
                $message,
                array_merge($additionalData, [
                    'type' => 'system'
                ]),
                $notification->id
            );

            Log::info("System notification sent to user {$userId}: {$title}");
            
        } catch (\Exception $e) {
            Log::error("Failed to send system notification: " . $e->getMessage());
        }
    }
}