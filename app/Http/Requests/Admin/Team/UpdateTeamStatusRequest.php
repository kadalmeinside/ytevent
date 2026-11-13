<?php

namespace App\Http\Requests\Admin\Team;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:disqualified,active'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status tujuan wajib dipilih.',
            'reason.required' => 'Alasan perubahan status tim wajib diisi.',
            'reason.min' => 'Alasan perubahan status tim minimal 5 karakter.',
        ];
    }
}

