<?php

namespace App\Http\Requests\Admin\Tournament;

use Illuminate\Foundation\Http\FormRequest;

class GenerateKnockoutMatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage' => ['required', 'string', 'in:round_of_16,quarter_final,semi_final,final,third_place'],
            'start_time' => ['nullable', 'date'],
            'interval_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'pitches' => ['nullable', 'string', 'max:255'],
        ];
    }
}

