<?php

namespace App\Http\Resources;

use App\Enums\ProductType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->image_urls;
        $type = $this->type;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $type->value,
            'type_label' => $type->label(),
            'description' => $this->description,
            'thumbnail' => $images[0] ?? null,
            'images' => $images,
            'base_fee' => (int) $this->base_fee,
            'starting_price' => isset($this->min_price)
                ? (int) $this->min_price + (int) $this->base_fee
                : null,
            'rating_avg' => round((float) ($this->rating_avg ?? 0), 1),
            'rating_count' => (int) ($this->rating_count ?? 0),
            'in_stock' => (int) ($this->total_stock ?? 0) > 0,

            // Petunjuk untuk Flutter: popup apa yang harus ditampilkan saat memesan.
            'options' => [
                'needs_iphone_model' => $type !== ProductType::UnlockImei,
                'needs_grade' => $type === ProductType::Sparepart,
                'needs_duration' => $type === ProductType::UnlockImei,
                'inspection_only' => $type === ProductType::Matot, // hanya biaya ongkir/pengecekan
            ],

            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}