<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'type',
        'url',
        'thumbnail_url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function mediable()
    {
        return $this->morphTo();
    }
}
