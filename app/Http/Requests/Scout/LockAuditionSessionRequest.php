<?php

namespace App\Http\Requests\Scout;

use Illuminate\Foundation\Http\FormRequest;

class LockAuditionSessionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['audition_stage_id' => ['required', 'integer', 'exists:audition_stages,id']];
    }
}
