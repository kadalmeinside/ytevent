<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyPlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('screening.verify_player') ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['passed', 'rejected'])],
            'rejection_reason' => [
                Rule::requiredIf(fn () => $this->input('action') === 'rejected'),
                'nullable',
                'string',
                'min:10',
                'max:1000',
            ],
            'documents' => ['nullable', 'array'],
            'documents.*.id' => ['required', 'integer', 'exists:player_documents,id'],
            'documents.*.validation_status' => ['required', 'string', Rule::in(['valid', 'invalid', 'pending'])],
            'documents.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Keputusan verifikasi pemain wajib ditentukan.',
            'action.in' => 'Keputusan harus berupa Sah / Lolos (passed) atau Ditolak (rejected).',
            'rejection_reason.required' => 'Alasan penolakan wajib diisi dengan jelas agar manajer tim dapat melakukan perbaikan dokumen.',
            'rejection_reason.min' => 'Alasan penolakan minimal 10 karakter.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 1000 karakter.',
            'documents.*.validation_status.in' => 'Status validasi dokumen tidak sah.',
        ];
    }
}
