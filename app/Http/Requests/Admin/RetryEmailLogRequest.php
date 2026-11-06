<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RetryEmailLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('email_logs.manage');
    }

    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_id.integer' => 'Filter kompetisi tidak valid.',
            'event_id.exists' => 'Kompetisi yang dipilih tidak ditemukan.',
        ];
    }
}

