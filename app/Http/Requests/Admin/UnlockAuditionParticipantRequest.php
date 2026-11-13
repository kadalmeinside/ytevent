<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UnlockAuditionParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('screening.unlock_player') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.max' => 'Alasan buka kunci maksimal 500 karakter.',
        ];
    }
}

