<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianSchedule extends Model
{
    protected $fillable = ['technician_id', 'order_id', 'date', 'time_slot'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}