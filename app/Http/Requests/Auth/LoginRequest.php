<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email yang dimasukkan tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ];
    }

    public function authenticate(bool $requireAdminPortal = false): User
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => strtolower(trim((string) $this->input('email'))),
            'password' => (string) $this->input('password'),
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'email' => 'Kombinasi alamat email atau kata sandi tidak sesuai.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Akun Anda saat ini tidak aktif. Silakan hubungi panitia.',
            ]);
        }

        if ($requireAdminPortal && ! $user->hasAnyRole(['superadmin', 'finance', 'screening_officer'])) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'email' => 'Akun ini tidak memiliki hak akses ke Portal Internal Panitia. Silakan login melalui Portal Manajer Tim.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $this->ip(),
        ]);

        return $user;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan login gagal. Silakan tunggu {$seconds} detik sebelum mencoba kembali.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')) . '|' . $this->ip());
    }
}

