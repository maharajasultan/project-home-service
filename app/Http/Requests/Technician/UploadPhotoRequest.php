<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:before,after'],
            // mimes (bukan "image") agar file SVG tidak lolos.
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Jenis foto wajib diisi (before atau after).',
            'type.in' => 'Jenis foto harus before atau after.',
            'photo.required' => 'Pilih foto terlebih dahulu.',
            'photo.file' => 'Berkas yang diunggah tidak valid.',
            'photo.mimes' => 'Format foto harus JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
            'photo.uploaded' => 'Foto gagal diunggah. Ukuran terlalu besar atau koneksi terputus.',
        ];
    }
}