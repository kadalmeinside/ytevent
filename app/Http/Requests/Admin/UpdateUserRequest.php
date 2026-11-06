<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    public function rules(): array
    {
        /** @var User|string|int|null $targetUser */
        $targetUser = $this->route('user');
        $targetUserId = $targetUser instanceof User ? $targetUser->id : (is_numeric($targetUser) ? (int) $targetUser : null);

        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:160',
                Rule::unique('users', 'email')->ignore($targetUserId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', Rule::in(['superadmin', 'finance', 'screening_officer', 'team_manager'])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap pengguna wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique' => 'Alamat email ini telah digunakan oleh akun lain.',
            'role.required' => 'Pilih peran (role) akses untuk pengguna ini.',
            'password.min' => 'Kata sandi baru minimal terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ];
    }
}
