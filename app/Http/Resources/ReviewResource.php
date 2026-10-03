<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Review */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'target_type' => $this->target_type,
            'target_id' => (int) $this->target_id,
            'order_code' => $this->whenLoaded('order', fn() => $this->order?->order_code),
            'user' => [
                'name' => $this->user?->name ?? 'Pengguna',
                'avatar_url' => MediaUrl::resolve($this->user?->avatar),
            ],
            'reply' => $this->reply,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
