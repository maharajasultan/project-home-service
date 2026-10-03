<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPhoto extends Model
{
    protected $fillable = ['order_id', 'type', 'path'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => MediaUrl::resolve($this->path));
    }
}