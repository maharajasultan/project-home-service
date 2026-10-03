<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianLocation extends Model
{
    protected $fillable = ['technician_id', 'order_id', 'latitude', 'longitude'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}