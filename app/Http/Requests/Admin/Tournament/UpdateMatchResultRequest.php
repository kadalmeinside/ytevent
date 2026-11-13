<?php

namespace App\Http\Requests\Admin\Tournament;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:scheduled,ongoing,completed,postponed'],
            'home_score' => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:99'],
            'home_penalty_score' => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_penalty_score' => ['nullable', 'integer', 'min:0', 'max:99'],
            'kickoff_at' => ['nullable', 'date'],
            'pitch_name' => ['nullable', 'string', 'max:80'],
            'referee_name' => ['nullable', 'string', 'max:120'],
            'match_notes' => ['nullable', 'string', 'max:1000'],
            'events' => ['nullable', 'array'],
            'events.*.team_id' => ['required', 'exists:teams,id'],
            'events.*.player_id' => ['nullable', 'exists:players,id'],
            'events.*.event_type' => ['required', 'in:goal,penalty_goal,own_goal,yellow_card,second_yellow_card,red_card'],
            'events.*.minute' => ['nullable', 'integer', 'min:1', 'max:150'],
            'events.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}

