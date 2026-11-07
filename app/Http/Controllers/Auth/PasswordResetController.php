<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function showForgotPassword(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request, EmailNotificationService $emailService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ], [
            'email.required' => 'Alamat email terdaftar wajib diisi.',
            'email.email' => 'Format alamat email yang dimasukkan tidak valid.',
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::query()->where('email', $email)->where('is_active', true)->first();

        if ($user) {
            $plainToken = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($plainToken),
                    'created_at' => now(),
                ]
            );

            $resetUrl = route('password.reset', [
                'token' => $plainToken,
                'email' => $user->email,
            ]);

            $emailService->sendPasswordReset($user, $resetUrl);
        }

        return redirect()->back()
            ->with('success', 'Jika alamat email tersebut terdaftar di sistem kami, tautan untuk mengatur ulang kata sandi telah dikirimkan ke kotak masuk Anda.');
    }

    public function showResetPassword(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', 'confirmed'],
        ], [
            'token.required' => 'Token pemulihan kata sandi wajib ada.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal terdiri dari 8 karakter.',
            'password.regex' => 'Kata sandi baru wajib mengandung kombinasi huruf dan angka.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $email = strtolower(trim($validated['email']));
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        $isExpired = ! $record || ! $record->created_at || Carbon::parse($record->created_at)->addMinutes(60)->isPast();
        $isValidToken = $record && ! $isExpired && Hash::check($validated['token'], $record->token);

        if (! $isValidToken) {
            throw ValidationException::withMessages([
                'email' => 'Tautan pemulihan kata sandi tidak valid atau telah kedaluwarsa (lebih dari 60 menit). Silakan ajukan permohonan ulang.',
            ]);
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Akun pengguna tidak ditemukan.',
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return redirect()->route('login')
            ->with('success', 'Kata sandi Anda berhasil diatur ulang. Silakan masuk menggunakan kata sandi baru.');
    }
}

