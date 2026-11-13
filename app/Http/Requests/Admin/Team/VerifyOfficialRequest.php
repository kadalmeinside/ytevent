<?php

namespace App\Http\Requests\Admin\Team;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOfficialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'screening_status' => ['required', 'string', 'in:passed,rejected'],
            'verification_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'screening_status.required' => 'Keputusan verifikasi ofisial wajib dipilih.',
            'screening_status.in' => 'Status verifikasi ofisial tidak valid.',
        ];
    }
}

