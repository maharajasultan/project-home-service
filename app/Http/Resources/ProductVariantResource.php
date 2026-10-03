<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductVariant */
class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $baseFee = $this->relationLoaded('product') ? (int) $this->product->base_fee : 0;

        return [
            'id' => $this->id,
            'iphone_model_id' => $this->iphone_model_id,
            'iphone_model' => $this->iphoneModel?->name,
            'grade' => $this->grade,
            'grade_label' => $this->grade ? ucfirst($this->grade) : null,
            'duration_months' => $this->duration_months,
            'duration_label' => $this->duration_months ? "{$this->duration_months} Bulan" : null,
            'price' => (int) $this->price,
            'final_price' => (int) $this->price + $baseFee, // harga + ongkir/pengecekan (Matot)
            'stock' => (int) $this->stock,
            'available' => $this->stock > 0,
        ];
    }
}