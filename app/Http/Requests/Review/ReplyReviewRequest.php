<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class ReplyReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reply' => trim(strip_tags((string) $this->input('reply')))]);
    }

    public function rules(): array
    {
        return [
            'reply' => ['required', 'string', 'min:2', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reply.required' => 'Balasan tidak boleh kosong.',
            'reply.min' => 'Balasan terlalu singkat.',
            'reply.max' => 'Balasan maksimal 500 karakter.',
        ];
    }
}