<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class TechnicianJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;
        $photos = $this->relationLoaded('photos') ? $this->photos : collect();
        $before = $photos->where('type', 'before')->values();
        $after = $photos->where('type', 'after')->values();

        $max = (int) config('reaple.technician.max_photos_per_type');
        $opensAt = $this->scheduled_at->copy()->subHours((int) config('reaple.technician.trip_hours_before'));
        $inProgress = $status === OrderStatus::InProgress;

        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'status' => $status->value,
            'status_label' => match ($status) {
                OrderStatus::Paid => 'Pesanan Masuk',
                OrderStatus::OnTheWay => 'Dalam Perjalanan',
                OrderStatus::InProgress => 'Sedang Dikerjakan',
                OrderStatus::Completed => 'Selesai',
                default => $status->label(),
            },
            // Tab dashboard teknisi: incoming | active | completed
            'tab' => match ($status) {
                OrderStatus::Paid => 'incoming',
                OrderStatus::OnTheWay, OrderStatus::InProgress => 'active',
                default => 'completed',
            },

            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),

            'customer' => [
                'name' => $this->user?->name,
                'phone_wa' => $this->phone_wa,
                'avatar_url' => MediaUrl::resolve($this->user?->avatar),
            ],

            // Alamat tujuan
            'address' => $this->address,
            'district' => $this->district,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'notes' => $this->notes,

            // Detail pergantian sparepart / service
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'variant_label' => $item->variant_label,
                'product_type' => $item->product?->type?->value,
                'inspection_only' => $item->product?->type?->value === 'matot', // teknisi hanya mencari kerusakan
                'qty' => (int) $item->qty,
            ])->values()),
            'total' => (int) $this->total,

            'photos' => [
                'before' => $before->map(fn ($p) => ['id' => $p->id, 'url' => $p->url])->all(),
                'after' => $after->map(fn ($p) => ['id' => $p->id, 'url' => $p->url])->all(),
                'max_per_type' => $max,
            ],

            // Flutter cukup membaca flag ini untuk menampilkan atau menonaktifkan tombol.
            'actions' => [
                'can_start_trip' => $status === OrderStatus::Paid && now()->gte($opensAt),
                'start_trip_available_at' => $status === OrderStatus::Paid ? $opensAt->toIso8601String() : null,
                'can_share_location' => $status === OrderStatus::OnTheWay,
                'can_upload_before' => in_array($status, [OrderStatus::OnTheWay, OrderStatus::InProgress], true)
                    && $before->count() < $max,
                'can_upload_after' => $inProgress && $after->count() < $max,
                'can_delete_photo' => $inProgress,
                'can_complete' => $inProgress && $before->isNotEmpty() && $after->isNotEmpty(),
            ],

            'server_time' => now()->toIso8601String(),
        ];
    }
}