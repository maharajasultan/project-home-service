<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => $isCreate ? ['required', Rule::enum(ProductType::class)] : ['nullable'],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_fee' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama produk wajib diisi.',
            'type.required' => 'Pilih jenis produk.',
            'type.enum' => 'Jenis produk tidak valid.',
            'description.max' => 'Deskripsi maksimal 2000 karakter.',
            'base_fee.integer' => 'Biaya ongkir/pengecekan harus berupa angka.',
            'base_fee.min' => 'Biaya ongkir/pengecekan tidak boleh negatif.',
            'images.max' => 'Maksimal 6 foto.',
            'images.*.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'images.*.max' => 'Ukuran tiap foto maksimal 3 MB.',
            'images.*.uploaded' => 'Foto gagal diunggah. Ukuran terlalu besar.',
        ];
    }
}