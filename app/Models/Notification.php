<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sender_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    // Notification types
    const TYPE_MESSAGE = 'message';
    const TYPE_LIKE = 'like';
    const TYPE_COMMENT = 'comment';
    const TYPE_FOLLOW = 'follow';
    const TYPE_SYSTEM = 'system';

    /**
     * Get the user that owns the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who sent the notification.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Check if the notification has been read.
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): bool
    {
        if ($this->isRead()) {
            return true;
        }

        return $this->update(['read_at' => now()]);
    }

    /**
     * Scope to get unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to get read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope to get notifications by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Create a message notification.
     */
    public static function createMessageNotification(int $userId, int $senderId, string $messageContent): self
    {
        return self::create([
            'user_id' => $userId,
            'sender_id' => $senderId,
            'type' => self::TYPE_MESSAGE,
            'title' => 'New Message',
            'message' => $messageContent,
            'data' => [
                'type' => 'message',
                'sender_id' => $senderId
            ]
        ]);
    }

    /**
     * Create a like notification.
     */
    public static function createLikeNotification(int $userId, int $senderId, int $postId): self
    {
        return self::create([
            'user_id' => $userId,
            'sender_id' => $senderId,
            'type' => self::TYPE_LIKE,
            'title' => 'Post Liked',
            'message' => 'liked your post',
            'data' => [
                'post_id' => $postId, 
                'type' => 'like',
                'sender_id' => $senderId
            ]
        ]);
    }

    /**
     * Create a comment notification.
     */
    public static function createCommentNotification(int $userId, int $senderId, int $postId, string $commentContent): self
    {
        return self::create([
            'user_id' => $userId,
            'sender_id' => $senderId,
            'type' => self::TYPE_COMMENT,
            'title' => 'New Comment',
            'message' => 'commented: ' . substr($commentContent, 0, 50) . (strlen($commentContent) > 50 ? '...' : ''),
            'data' => [
                'post_id' => $postId, 
                'type' => 'comment',
                'sender_id' => $senderId
            ]
        ]);
    }

    /**
     * Create a follow notification.
     */
    public static function createFollowNotification(int $userId, int $senderId): self
    {
        return self::create([
            'user_id' => $userId,
            'sender_id' => $senderId,
            'type' => self::TYPE_FOLLOW,
            'title' => 'New Follower',
            'message' => 'started following you',
            'data' => [
                'type' => 'follow',
                'sender_id' => $senderId
            ]
        ]);
    }

    /**
     * Create a system notification.
     */
    public static function createSystemNotification(int $userId, string $title, string $message, array $data = []): self
    {
        return self::create([
            'user_id' => $userId,
            'sender_id' => null,
            'type' => self::TYPE_SYSTEM,
            'title' => $title,
            'message' => $message,
            'data' => array_merge($data, [
                'type' => 'system',
                'sender_id' => null
            ])
        ]);
    }
}