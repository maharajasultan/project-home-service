<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_code', 'user_id', 'technician_id', 'status', 'address', 'district',
        'latitude', 'longitude', 'phone_wa', 'notes', 'scheduled_at',
        'subtotal', 'service_fee', 'total', 'expired_at', 'paid_at', 'completed_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'scheduled_at' => 'datetime',
        'expired_at' => 'datetime',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(OrderPhoto::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function warrantyClaims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(TechnicianSchedule::class);
    }

    public function isExpired(): bool
    {
        return $this->status === OrderStatus::Pending
            && $this->expired_at !== null
            && $this->expired_at->isPast();
    }

    /** Garansi berlaku 3 bulan sejak pesanan selesai. */
    public function warrantyValidUntil(): ?\Illuminate\Support\Carbon
    {
        return $this->completed_at?->copy()->addMonths(3);
    }

    public function isUnderWarranty(): bool
    {
        $until = $this->warrantyValidUntil();

        return $until !== null && $until->isFuture();
    }
}