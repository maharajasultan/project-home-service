<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_variant_id.required' => 'Pilih varian produk terlebih dahulu.',
            'product_variant_id.exists' => 'Varian produk tidak ditemukan.',
            'qty.min' => 'Jumlah minimal 1.',
            'qty.max' => 'Jumlah maksimal 5 per item.',
        ];
    }
}