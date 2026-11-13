<?php

namespace App\Http\Requests\Admin\Tournament;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTournamentGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_count' => ['required', 'integer', 'min:1', 'max:16'],
            'only_locked_roster' => ['nullable', 'boolean'],
        ];
    }
}

