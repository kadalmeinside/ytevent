<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CheckInAuditionParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100'],
            'stage_id' => ['required', 'integer', 'exists:audition_stages,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
