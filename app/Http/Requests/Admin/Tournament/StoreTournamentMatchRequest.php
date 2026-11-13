<?php

namespace App\Http\Requests\Admin\Tournament;

use Illuminate\Foundation\Http\FormRequest;

class StoreTournamentMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage' => ['required', 'string'],
            'event_group_id' => ['nullable', 'exists:event_groups,id'],
            'home_team_id' => ['nullable', 'exists:teams,id'],
            'away_team_id' => ['nullable', 'exists:teams,id'],
            'kickoff_at' => ['nullable', 'date'],
            'pitch_name' => ['nullable', 'string', 'max:80'],
            'referee_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}

