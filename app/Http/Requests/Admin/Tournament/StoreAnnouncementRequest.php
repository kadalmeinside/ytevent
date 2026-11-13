<?php

namespace App\Http\Requests\Admin\Tournament;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:3000'],
            'meeting_link' => ['nullable', 'url', 'max:255'],
            'is_pinned' => ['nullable', 'boolean'],
        ];
    }
}

