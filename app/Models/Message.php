<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'content',
        'is_read',
        'type',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_read' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the sender of the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the receiver of the message.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Scope for general messages (mutual following).
     */
    public function scopeGeneral($query)
    {
        return $query->where('type', 'general');
    }

    /**
     * Scope for request messages (one-way or no following).
     */
    public function scopeRequests($query)
    {
        return $query->where('type', 'request');
    }

    /**
     * Scope for unread messages.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Mark the message as read.
     */
    public function markAsRead(): bool
    {
        return $this->update(['is_read' => true]);
    }

    /**
     * Check if this is a general message (mutual following).
     */
    public function isGeneral(): bool
    {
        return $this->type === 'general';
    }

    /**
     * Check if this is a request message.
     */
    public function isRequest(): bool
    {
        return $this->type === 'request';
    }

    /**
     * Determine message type based on following relationship.
     */
    public static function determineType(int $senderId, int $receiverId): string
    {
        $sender = User::find($senderId);
        $receiver = User::find($receiverId);

        if (!$sender || !$receiver) {
            return 'request';
        }

        // Check if they follow each other (mutual following = general)
        $senderFollowsReceiver = $sender->following()->where('following_id', $receiverId)->exists();
        $receiverFollowsSender = $receiver->following()->where('following_id', $senderId)->exists();

        return ($senderFollowsReceiver && $receiverFollowsSender) ? 'general' : 'request';
    }
}
