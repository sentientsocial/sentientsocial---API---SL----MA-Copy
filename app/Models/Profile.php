<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'username',
        'display_name',
        'bio',
        'phone',
        'date_of_birth',
        'avatar',
        'background_image',
        'meditation_minutes',
        'streak_count',
        'last_meditation_at',
        'meditation_goals',
        'weekly_meditation_volume',
        'posts_count',
    ];

    protected $casts = [
        'last_meditation_at' => 'datetime',
        'date_of_birth' => 'date',
        'meditation_goals' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the actual post count from the database
     */
    public function getActualPostsCount()
    {
        return $this->user->posts()->count();
    }

    /**
     * Increment posts count
     */
    public function incrementPostsCount()
    {
        $this->increment('posts_count');
        return $this;
    }

    /**
     * Decrement posts count
     */
    public function decrementPostsCount()
    {
        $this->decrement('posts_count');
        return $this;
    }

    /**
     * Sync posts count with actual count
     */
    public function syncPostsCount()
    {
        $actualCount = $this->getActualPostsCount();
        $this->update(['posts_count' => $actualCount]);
        return $this;
    }
}
