<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class InlineLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'redirect_to' => ['nullable', 'string', 'in:register,register-audition'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi untuk masuk.',
        ];
    }
}
