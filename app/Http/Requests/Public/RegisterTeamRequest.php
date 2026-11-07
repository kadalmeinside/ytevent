<?php

namespace App\Http\Requests\Public;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Event|null $event */
        $event = $this->route('event');
        $isGuest = $this->user() === null;

        $rules = [
            'team_name' => [
                'required',
                'string',
                'min:3',
                'max:100',
                $event
                    ? Rule::unique('teams', 'name')
                        ->where('event_id', $event->id)
                        ->whereNull('deleted_at')
                    : 'string',
            ],
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'origin_association' => ['required', 'string', 'min:3', 'max:120'],
            'manager_name' => ['required', 'string', 'min:3', 'max:100'],
            'manager_whatsapp' => [
                'required',
                'string',
                'regex:/^(\+62|62|0)8[1-9][0-9]{7,12}$/',
            ],
            'head_coach_name' => ['required', 'string', 'min:3', 'max:100'],
            'head_coach_license' => ($event && ($event->show_coach_license ?? true) && ($event->require_coach_license ?? false))
                ? ['required', 'string', 'min:2', 'max:80']
                : ['nullable', 'string', 'max:80'],
            'secretariat_address' => ['nullable', 'string', 'max:500'],
            'terms_accepted' => ['accepted'],
        ];

        if ($isGuest) {
            $rules['email'] = ['required', 'string', 'email', 'max:160', 'unique:users,email'];
            $rules['password'] = [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
                'confirmed',
            ];
            $rules['password_confirmation'] = ['required', 'string'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'team_name.required' => 'Nama tim wajib diisi.',
            'team_name.min' => 'Nama tim minimal terdiri dari 3 karakter.',
            'team_name.max' => 'Nama tim maksimal 100 karakter.',
            'team_name.unique' => 'Nama tim ini sudah terdaftar pada kompetisi yang dipilih.',
            'logo.required' => 'Logo resmi tim wajib diunggah.',
            'logo.file' => 'Logo resmi tim wajib berupa berkas gambar yang valid.',
            'logo.mimes' => 'Format berkas logo harus berupa PNG atau JPG.',
            'logo.max' => 'Ukuran berkas logo tidak boleh melebihi 2 MB.',
            'origin_association.required' => 'Kota atau Asosiasi Asal (Askot/Askab) wajib diisi.',
            'origin_association.min' => 'Kota atau Asosiasi Asal (Askot/Askab) minimal 3 karakter.',
            'manager_name.required' => 'Nama lengkap manajer atau penanggung jawab wajib diisi.',
            'manager_name.min' => 'Nama lengkap manajer minimal 3 karakter.',
            'email.required' => 'Alamat email aktif wajib diisi.',
            'email.email' => 'Format alamat email yang dimasukkan tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan login atau gunakan email lain.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter dan wajib mengandung huruf serta angka.',
            'password.regex' => 'Kata sandi minimal 8 karakter dan wajib mengandung huruf serta angka.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok dengan kata sandi yang dimasukkan.',
            'password_confirmation.required' => 'Konfirmasi kata sandi wajib diisi.',
            'manager_whatsapp.required' => 'Nomor telepon/WhatsApp aktif wajib diisi.',
            'manager_whatsapp.regex' => 'Masukkan nomor WhatsApp Indonesia yang valid (contoh: 081234567890).',
            'head_coach_name.required' => 'Nama pelatih kepala wajib diisi.',
            'head_coach_name.min' => 'Nama pelatih kepala minimal 3 karakter.',
            'head_coach_license.required' => 'Nomor atau jenis lisensi pelatih kepala wajib diisi sesuai regulasi kompetisi ini.',
            'head_coach_license.min' => 'Nomor atau jenis lisensi pelatih kepala minimal 2 karakter.',
            'terms_accepted.accepted' => 'Anda wajib menyetujui syarat, ketentuan, dan regulasi kompetisi untuk melanjutkan pendaftaran.',
        ];
    }
}

