<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CartItem */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variant = $this->variant;
        $product = $variant->product;
        $images = $product->image_urls;

        $reason = null;
        if (! $variant->is_active || ! $product->is_active) {
            $reason = 'Produk ini sudah tidak tersedia.';
        } elseif ($variant->stock < $this->qty) {
            $reason = $variant->stock > 0 ? "Stok tersisa {$variant->stock}." : 'Stok habis.';
        }

        return [
            'id' => $this->id,
            'qty' => (int) $this->qty,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type->value,
                'thumbnail' => $images[0] ?? null,
            ],
            'variant' => [
                'id' => $variant->id,
                'label' => $variant->label(),
                'iphone_model' => $variant->iphoneModel?->name,
                'grade' => $variant->grade,
                'duration_months' => $variant->duration_months,
            ],
            'unit_price' => (int) $variant->price,
            'base_fee' => (int) $product->base_fee,
            'line_total' => ((int) $variant->price + (int) $product->base_fee) * $this->qty,
            'available' => $reason === null,
            'unavailable_reason' => $reason,
        ];
    }
}