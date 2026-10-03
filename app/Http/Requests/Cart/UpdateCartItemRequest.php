<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'qty.required' => 'Jumlah wajib diisi.',
            'qty.min' => 'Jumlah minimal 1.',
            'qty.max' => 'Jumlah maksimal 5 per item.',
        ];
    }
}