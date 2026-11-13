<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;

class SaveMatchLineupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'starting_player_ids' => ['required', 'array', 'min:1'],
            'starting_player_ids.*' => ['integer', 'exists:players,id'],
            'substitute_player_ids' => ['nullable', 'array'],
            'substitute_player_ids.*' => ['integer', 'exists:players,id'],
            'captain_player_id' => ['nullable', 'integer', 'exists:players,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'starting_player_ids.required' => 'Pilih minimal 1 pemain utama (Starting Lineup).',
            'starting_player_ids.min' => 'Pilih minimal 1 pemain utama (Starting Lineup).',
        ];
    }
}

