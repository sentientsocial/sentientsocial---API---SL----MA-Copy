<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyHealthMetric extends Model
{
    protected $fillable = [
        'user_id',
        'terra_user_id',
        'date',
        'steps',
        'distance_meters',
        'total_calories',
        'active_calories',
        'resting_heart_rate',
        'activity_seconds',
        'source',
        'raw_data',
    ];

    protected $casts = [
        'date' => 'date',
        'steps' => 'integer',
        'distance_meters' => 'decimal:2',
        'total_calories' => 'decimal:2',
        'active_calories' => 'decimal:2',
        'resting_heart_rate' => 'integer',
        'activity_seconds' => 'integer',
        'raw_data' => 'array',
    ];

    /**
     * Get the user that owns the health metric.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
