<?php

namespace App\Http\Requests\Manager;

use App\Models\Team;
use App\Models\TeamOfficial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:100'],
            'role' => ['required', Rule::in(array_keys(TeamOfficial::ROLES))],
            'license_number' => ['nullable', 'string', 'max:80'],
            'phone' => [
                'nullable',
                'string',
                'regex:/^(\+62|62|0)8[1-9][0-9]{7,12}$/',
            ],
            'license_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap pelatih atau ofisial wajib diisi.',
            'full_name.min' => 'Nama lengkap ofisial minimal 3 karakter.',
            'role.required' => 'Jabatan ofisial wajib dipilih.',
            'phone.regex' => 'Masukkan nomor WhatsApp Indonesia yang valid (contoh: 081234567890).',
            'license_file.mimes' => 'Berkas sertifikat lisensi harus berformat JPG, PNG, atau PDF (maks 2 MB).',
            'license_file.max' => 'Ukuran sertifikat lisensi tidak boleh melebihi 2 MB.',
            'photo.mimes' => 'Pas foto ofisial harus berformat JPG atau PNG (maks 2 MB).',
            'photo.max' => 'Ukuran pas foto ofisial tidak boleh melebihi 2 MB.',
        ];
    }
}

