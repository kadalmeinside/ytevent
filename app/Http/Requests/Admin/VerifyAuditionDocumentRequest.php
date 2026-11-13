<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyAuditionDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('screening.verify_player') ?? false;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', Rule::in(['birth_certificate', 'family_card', 'additional_doc'])],
            'validation_status' => ['required', 'string', Rule::in(['valid', 'invalid', 'pending'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'Jenis dokumen wajib ditentukan.',
            'document_type.in' => 'Jenis dokumen tidak dikenali.',
            'validation_status.required' => 'Status validasi dokumen wajib dipilih.',
            'validation_status.in' => 'Status validasi dokumen harus berupa Valid, Invalid, atau Pending.',
            'notes.max' => 'Catatan verifikator maksimal 500 karakter.',
        ];
    }
}

