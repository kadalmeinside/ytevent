<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MarkPaymentRefundedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refund_notes' => ['required', 'string', 'min:5', 'max:500'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_reference' => ['nullable', 'string', 'max:120'],
            'refund_method' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'refund_notes.required' => 'Catatan atau nomor referensi transfer pengembalian dana wajib diisi.',
            'refund_notes.min' => 'Catatan atau referensi transfer minimal 5 karakter.',
        ];
    }
}

