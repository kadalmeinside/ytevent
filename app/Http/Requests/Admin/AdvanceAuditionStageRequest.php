<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdvanceAuditionStageRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:audition_participants,id'],
            'target_stage_status' => ['required', 'string', 'in:registered,day_1_passed,day_1_eliminated,day_2_passed,day_2_eliminated,final_selected'],
            'dispatch_email' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
