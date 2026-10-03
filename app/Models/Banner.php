<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = ['title', 'image', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => MediaUrl::resolve($this->image));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}