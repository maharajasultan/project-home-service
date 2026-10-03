<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technician.rating' => ['required', 'integer', 'between:1,5'],
            'technician.comment' => ['nullable', 'string', 'max:500'],

            'products' => ['required', 'array', 'min:1', 'max:20'],
            'products.*.product_id' => ['required', 'integer', 'distinct'],
            'products.*.rating' => ['required', 'integer', 'between:1,5'],
            'products.*.comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'technician.rating.required' => 'Beri rating untuk teknisi.',
            'technician.rating.between' => 'Rating harus antara 1 sampai 5.',
            'technician.comment.max' => 'Komentar teknisi maksimal 500 karakter.',
            'products.required' => 'Beri rating untuk produk.',
            'products.*.product_id.distinct' => 'Produk tidak boleh dinilai dua kali.',
            'products.*.rating.required' => 'Beri rating untuk setiap produk.',
            'products.*.rating.between' => 'Rating harus antara 1 sampai 5.',
            'products.*.comment.max' => 'Komentar produk maksimal 500 karakter.',
        ];
    }
}