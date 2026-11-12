<?php

namespace App\Http\Requests\Manager;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateTeamProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');

        return $team && ($this->user()?->can('update', $team) ?? false);
    }

    public function rules(): array
    {
        /** @var Team|null $team */
        $team = $this->route('team');
        $event = $team?->event;
        $requireLicense = (bool) ($event && ($event->show_coach_license ?? true) && ($event->require_coach_license ?? false));

        return [
            'origin_association' => ['required', 'string', 'min:3', 'max:140'],
            'secretariat_address' => ['nullable', 'string', 'max:500'],
            'manager_name' => ['required', 'string', 'min:3', 'max:100'],
            'manager_whatsapp' => [
                'required',
                'string',
                'regex:/^(\+62|62|0)8[1-9][0-9]{7,12}$/',
            ],
            'head_coach_name' => ['required', 'string', 'min:3', 'max:100'],
            'head_coach_license' => $requireLicense
                ? ['required', 'string', 'min:2', 'max:80']
                : ['nullable', 'string', 'max:80'],
            'home_jersey_color' => ['nullable', 'string', 'max:60'],
            'away_jersey_color' => ['nullable', 'string', 'max:60'],
            'gk_jersey_color' => ['nullable', 'string', 'max:60'],
            'logo' => ['nullable', File::image()->max(2048), 'mimes:png,jpg,jpeg'],
        ];
    }

    public function messages(): array
    {
        return [
            'origin_association.required' => 'Kota atau Asosiasi Asal (Askot/Askab) wajib diisi.',
            'manager_name.required' => 'Nama lengkap manajer atau penanggung jawab wajib diisi.',
            'manager_whatsapp.required' => 'Nomor telepon/WhatsApp aktif wajib diisi.',
            'manager_whatsapp.regex' => 'Masukkan nomor WhatsApp Indonesia yang valid (contoh: 081234567890).',
            'head_coach_name.required' => 'Nama pelatih kepala wajib diisi.',
            'head_coach_license.required' => 'Nomor atau jenis lisensi pelatih kepala wajib diisi sesuai regulasi kompetisi ini.',
            'head_coach_license.min' => 'Nomor atau jenis lisensi pelatih kepala minimal 2 karakter.',
            'logo.mimes' => 'Format berkas logo harus berupa PNG atau JPG.',
            'logo.max' => 'Ukuran berkas logo tidak boleh melebihi 2 MB.',
        ];
    }
}
