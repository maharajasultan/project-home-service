<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'avatar_url' => MediaUrl::resolve($this->avatar),
            'technician' => $this->when(
                $this->isTechnician() && $this->relationLoaded('technicianProfile') && $this->technicianProfile,
                fn () => [
                    'bio' => $this->technicianProfile->bio,
                    'avg_rating' => $this->technicianProfile->avg_rating,
                    'rating_count' => $this->technicianProfile->rating_count,
                ]
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}