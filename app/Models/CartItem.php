<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['user_id', 'product_variant_id', 'qty'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** Item masih bisa dibeli: varian dan produk aktif, stok cukup. */
    public function isAvailable(): bool
    {
        $variant = $this->variant;

        return $variant !== null
            && $variant->is_active
            && (bool) $variant->product?->is_active
            && $variant->stock >= $this->qty;
    }
}