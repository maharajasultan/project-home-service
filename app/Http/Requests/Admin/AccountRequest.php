<?php

namespace App\Http\Requests\Admin;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = PhoneNumber::normalize($this->input('phone'));

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => $phone === '' ? null : $phone,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $isUpdate = $id !== null;
        $isTechnician = $this->routeIs('admin.technicians.*');

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone' => [
                $isTechnician ? 'required' : 'nullable',
                'regex:/^08[1-9][0-9]{7,11}$/',
                Rule::unique('users', 'phone')->ignore($id),
            ],
            'password' => [
                $isUpdate ? 'nullable' : 'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'is_active' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($isTechnician) {
            $rules['bio'] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai akun lain.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.regex' => 'Nomor HP tidak valid. Contoh: 081234567890.',
            'phone.unique' => 'Nomor HP sudah dipakai akun lain.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.letters' => 'Kata sandi harus mengandung huruf.',
            'password.numbers' => 'Kata sandi harus mengandung angka.',
            'avatar.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'avatar.max' => 'Ukuran foto maksimal 2 MB.',
            'bio.max' => 'Bio maksimal 500 karakter.',
        ];
    }
}