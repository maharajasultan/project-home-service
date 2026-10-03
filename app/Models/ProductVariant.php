<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    public const GRADES = ['standar', 'premium', 'original'];

    protected $fillable = [
        'product_id',
        'iphone_model_id',
        'grade',
        'duration_months',
        'price',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function iphoneModel(): BelongsTo
    {
        return $this->belongsTo(IphoneModel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Label ringkas untuk snapshot order, contoh: "iPhone 13 - Premium". */
    public function label(): string
    {
        $parts = array_filter([
            $this->iphoneModel?->name,
            $this->grade ? ucfirst($this->grade) : null,
            $this->duration_months ? "{$this->duration_months} Bulan" : null,
        ]);

        return implode(' - ', $parts);
    }
    public function scopePurchasable($query)
    {
        return $query->where('is_active', true)
            ->whereHas('product', fn($p) => $p->where('is_active', true))
            ->where(fn($q) => $q->whereNull('iphone_model_id')
                ->orWhereHas('iphoneModel', fn($m) => $m->where('is_active', true)));
    }
}
