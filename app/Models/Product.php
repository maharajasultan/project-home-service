<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'description', 'images', 'base_fee', 'is_active',
    ];

    protected $casts = [
        'type' => ProductType::class,
        'images' => 'array',
        'is_active' => 'boolean',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** Ulasan produk (polimorfik manual: target_type = product). */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'target_id')->where('target_type', 'product');
    }

    protected function imageUrls(): Attribute
    {
        return Attribute::get(
            fn () => collect($this->images ?? [])->map(fn ($p) => MediaUrl::resolve($p))->filter()->values()->all()
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}