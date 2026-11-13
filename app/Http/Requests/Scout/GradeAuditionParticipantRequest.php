<?php

namespace App\Http\Requests\Scout;

use Illuminate\Foundation\Http\FormRequest;

class GradeAuditionParticipantRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'audition_participant_id' => ['required', 'integer', 'exists:audition_participants,id'],
            'audition_stage_id' => ['required', 'integer', 'exists:audition_stages,id'],
            'quick_grade' => ['nullable', 'string', 'in:A,B,C'],
            'rating_stars' => ['nullable', 'integer', 'min:1', 'max:5'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'coach_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
