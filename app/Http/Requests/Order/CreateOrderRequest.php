<?php

namespace App\Http\Requests\Order;

use App\Services\AddressValidatorService;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone_wa' => PhoneNumber::normalize($this->input('phone_wa'))]);
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'in:cart,direct'],

            // Wajib hanya untuk "Beli Langsung".
            'items' => ['required_if:source,direct', 'array', 'min:1', 'max:10'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:5'],

            'address' => ['required', 'string', 'min:15', 'max:500'],
            'district' => ['required', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'phone_wa' => ['required', 'regex:/^08[1-9][0-9]{7,11}$/'],
            'notes' => ['nullable', 'string', 'max:500'],

            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'technician_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['address', 'district', 'latitude', 'longitude'])) {
                return;
            }

            $errors = app(AddressValidatorService::class)->check(
                (string) $this->input('address'),
                (string) $this->input('district'),
                $this->filled('latitude') ? (float) $this->input('latitude') : null,
                $this->filled('longitude') ? (float) $this->input('longitude') : null,
            );

            foreach ($errors as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });
    }

    public function messages(): array
    {
        return [
            'source.required' => 'Sumber pesanan wajib diisi (cart atau direct).',
            'source.in' => 'Sumber pesanan harus cart atau direct.',
            'items.required_if' => 'Pilih minimal satu produk.',
            'items.*.product_variant_id.exists' => 'Varian produk tidak ditemukan.',
            'items.*.qty.max' => 'Jumlah maksimal 5 per item.',
            'address.required' => 'Alamat lengkap wajib diisi.',
            'address.min' => 'Alamat terlalu singkat. Tulis jalan, nomor, RT/RW, dan kelurahan.',
            'district.required' => 'Kecamatan wajib dipilih.',
            'phone_wa.required' => 'Nomor WhatsApp wajib diisi.',
            'phone_wa.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'scheduled_date.required' => 'Pilih tanggal kunjungan.',
            'scheduled_date.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'scheduled_time.required' => 'Pilih jam kunjungan.',
            'scheduled_time.date_format' => 'Format jam harus HH:MM.',
            'technician_id.required' => 'Pilih teknisi.',
            'technician_id.exists' => 'Teknisi tidak ditemukan.',
        ];
    }
}