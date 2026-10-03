<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;
        $isPending = $status === OrderStatus::Pending;

        $secondsRemaining = null;
        if ($isPending && $this->expired_at) {
            $secondsRemaining = max(0, (int) now()->diffInSeconds($this->expired_at, false));
        }

        $reviewed = $this->relationLoaded('reviews') && $this->reviews->isNotEmpty();
        $completed = $status === OrderStatus::Completed;
        $hasPendingClaim = $this->relationLoaded('warrantyClaims')
            && $this->warrantyClaims->where('status', 'pending')->isNotEmpty();

        return [
            'id' => $this->id,
            'order_code' => $this->order_code,

            'status' => $status->value,
            'status_label' => $status->label(),
            // Tab riwayat: pending | success | failed
            'tab' => match (true) {
                $isPending => 'pending',
                $status === OrderStatus::Failed => 'failed',
                default => 'success',
            },
            // Pesanan gagal ditampilkan disabled di Flutter.
            'is_clickable' => $status !== OrderStatus::Failed,
            'can_pay' => $isPending && $secondsRemaining > 0,

            'subtotal' => (int) $this->subtotal,
            'service_fee' => (int) $this->service_fee,
            'total' => (int) $this->total,

            // Countdown dihitung server. Flutter cukup menjalankan timer dari seconds_remaining.
            'expired_at' => $this->expired_at?->toIso8601String(),
            'seconds_remaining' => $secondsRemaining,
            'server_time' => now()->toIso8601String(),

            'address' => $this->address,
            'district' => $this->district,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone_wa' => $this->phone_wa,
            'notes' => $this->notes,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),

            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'variant_label' => $item->variant_label,
                'price' => (int) $item->price,
                'base_fee' => (int) $item->base_fee,
                'qty' => (int) $item->qty,
                'line_total' => ((int) $item->price + (int) $item->base_fee) * (int) $item->qty,
            ])->values()),

            'technician' => $this->whenLoaded('technician', fn() => $this->technician ? [
                'id' => $this->technician->id,
                'name' => $this->technician->name,
                'avatar_url' => MediaUrl::resolve($this->technician->avatar),
                'avg_rating' => (float) ($this->technician->technicianProfile?->avg_rating ?? 0),
            ] : null),

            'payment' => $this->whenLoaded('payment', fn() => $this->payment ? [
                'method' => $this->payment->method,
                'status' => $this->payment->status,
                'redirect_url' => $this->payment->redirect_url,
            ] : null),

            // Dipakai Flutter untuk menampilkan tombol (aktif setelah B6 dan B7 selesai).
            'can_chat' => in_array($status, OrderStatus::successful(), true),
            'can_track' => in_array($status, [OrderStatus::Paid, OrderStatus::OnTheWay, OrderStatus::InProgress], true),
            'photos' => $this->whenLoaded('photos', fn() => [
                'before' => $this->photos->where('type', 'before')->map(fn($p) => $p->url)->values(),
                'after' => $this->photos->where('type', 'after')->map(fn($p) => $p->url)->values(),
            ]),
            'can_review' => $completed && ! $reviewed,
            'can_claim_warranty' => $completed && $reviewed && $this->isUnderWarranty() && ! $hasPendingClaim,
            'warranty_valid_until' => $completed ? $this->warrantyValidUntil()?->toIso8601String() : null,
            'has_pending_claim' => $hasPendingClaim,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
