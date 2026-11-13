<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyAuditionParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('screening.verify_player') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['passed', 'rejected'])],
            'notes' => [
                Rule::requiredIf(fn () => $this->input('status') === 'rejected'),
                'nullable',
                'string',
                'min:10',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Keputusan verifikasi peserta wajib ditentukan.',
            'status.in' => 'Keputusan verifikasi tidak sah.',
            'notes.required' => 'Alasan penolakan / catatan revisi wajib diisi jika berkas ditolak (min. 10 karakter).',
            'notes.min' => 'Catatan revisi minimal berjumlah 10 karakter.',
            'notes.max' => 'Catatan revisi maksimal 1000 karakter.',
        ];
    }
}

