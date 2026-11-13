<?php

namespace App\Http\Requests\Admin\Tournament;

use Illuminate\Foundation\Http\FormRequest;

class GenerateGroupMatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_time' => ['nullable', 'date'],
            'interval_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'pitches' => ['nullable', 'string', 'max:255'],
        ];
    }
}

