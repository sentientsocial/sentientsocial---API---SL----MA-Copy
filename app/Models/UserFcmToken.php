<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFcmToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_id',
        'app_version',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    // Platform constants
    const PLATFORM_IOS = 'ios';
    const PLATFORM_ANDROID = 'android';
    const PLATFORM_WEB = 'web';
    const PLATFORM_UNKNOWN = 'unknown';

    /**
     * Get the user that owns the FCM token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get active tokens.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get tokens for a specific platform.
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Mark token as active and update last used timestamp.
     */
    public function markAsActive(): bool
    {
        return $this->update([
            'is_active' => true,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Mark token as inactive.
     */
    public function markAsInactive(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Create or update a user's FCM token.
     */
    public static function createOrUpdate(
        int $userId,
        string $token,
        string $platform = self::PLATFORM_UNKNOWN,
        ?string $deviceId = null,
        ?string $appVersion = null
    ): self {
        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'token' => $token,
            ],
            [
                'platform' => $platform,
                'device_id' => $deviceId,
                'app_version' => $appVersion,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Get all active tokens for a user.
     */
    public static function getActiveTokensForUser(int $userId): array
    {
        return self::where('user_id', $userId)
            ->active()
            ->pluck('token')
            ->toArray();
    }

    /**
     * Remove inactive tokens older than specified days.
     */
    public static function removeOldInactiveTokens(int $days = 30): int
    {
        return self::where('is_active', false)
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Remove tokens that haven't been used for a long time.
     */
    public static function removeUnusedTokens(int $days = 90): int
    {
        return self::where(function ($query) use ($days) {
            $query->whereNull('last_used_at')
                ->where('created_at', '<', now()->subDays($days));
        })
        ->orWhere('last_used_at', '<', now()->subDays($days))
        ->delete();
    }
}