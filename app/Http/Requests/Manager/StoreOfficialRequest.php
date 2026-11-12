<?php

namespace App\Http\Requests\Manager;

use App\Models\Team;
use App\Models\TeamOfficial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreOfficialRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');

        return $team && ($this->user()?->can('manageRoster', $team) ?? false);
    }

    public function rules(): array
    {
        /** @var Team|null $team */
        $team = $this->route('team');
        $event = $team?->event;
        $requireLicense = (bool) (
            $event
            && ($event->show_coach_license ?? true)
            && ($event->require_coach_license ?? false)
            && $this->input('role') === 'Head Coach'
        );

        return [
            'full_name' => ['required', 'string', 'min:3', 'max:100'],
            'role' => ['required', Rule::in(array_keys(TeamOfficial::ROLES))],
            'license_number' => $requireLicense
                ? ['required', 'string', 'min:2', 'max:80']
                : ['nullable', 'string', 'max:80'],
            'phone' => [
                'nullable',
                'string',
                'regex:/^(\+62|62|0)8[1-9][0-9]{7,12}$/',
            ],
            'license_file' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(2048)],
            'photo' => ['nullable', File::image()->max(2048), 'mimes:jpg,jpeg,png'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap pelatih atau ofisial wajib diisi.',
            'full_name.min' => 'Nama lengkap ofisial minimal 3 karakter.',
            'role.required' => 'Jabatan ofisial wajib dipilih.',
            'license_number.required' => 'Nomor lisensi kepelatihan untuk Pelatih Kepala (Head Coach) wajib diisi sesuai regulasi kompetisi ini.',
            'license_number.min' => 'Nomor lisensi kepelatihan minimal 2 karakter.',
            'phone.regex' => 'Masukkan nomor WhatsApp Indonesia yang valid (contoh: 081234567890).',
            'license_file.mimes' => 'Berkas sertifikat lisensi harus berformat JPG, PNG, atau PDF (maks 2 MB).',
            'license_file.max' => 'Ukuran sertifikat lisensi tidak boleh melebihi 2 MB.',
            'photo.mimes' => 'Pas foto ofisial harus berformat JPG atau PNG (maks 2 MB).',
            'photo.max' => 'Ukuran pas foto ofisial tidak boleh melebihi 2 MB.',
        ];
    }
}
