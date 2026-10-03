<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianProfile extends Model
{
    protected $fillable = ['user_id', 'bio', 'is_active', 'avg_rating', 'rating_count'];

    protected $casts = [
        'is_active' => 'boolean',
        'avg_rating' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}