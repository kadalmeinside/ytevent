<?php

namespace App\Http\Requests\Manager;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

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
            'head_coach_license' => ['nullable', 'string', 'max:80'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
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
            'logo.mimes' => 'Format berkas logo harus berupa PNG atau JPG.',
            'logo.max' => 'Ukuran berkas logo tidak boleh melebihi 2 MB.',
        ];
    }
}

