<?php

namespace App\Http\Requests\Admin\Tournament;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kickoff_at' => ['required', 'date'],
            'pitch_name' => ['nullable', 'string', 'max:80'],
            'referee_name' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'in:scheduled,ongoing,completed,postponed'],
            'match_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

