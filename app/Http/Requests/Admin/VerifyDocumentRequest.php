<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('screening.verify_player') ?? false;
    }

    public function rules(): array
    {
        return [
            'validation_status' => ['required', 'string', Rule::in(['valid', 'invalid', 'pending'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'validation_status.required' => 'Status validasi berkas dokumen wajib dipilih.',
            'validation_status.in' => 'Status dokumen harus berupa Valid, Invalid, atau Pending.',
            'notes.max' => 'Catatan verifikator maksimal 500 karakter.',
        ];
    }
}

