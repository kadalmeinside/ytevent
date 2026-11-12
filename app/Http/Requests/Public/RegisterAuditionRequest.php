<?php

namespace App\Http\Requests\Public;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterAuditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');
        $isLoggedIn = $this->user() !== null;
        $presets = $event->form_presets ?? Event::DEFAULT_FORM_PRESETS;

        $rules = [
            // Data Atlet Anak
            'full_name' => ['required', 'string', 'max:150'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'nik' => [
                'required',
                'string',
                'size:16',
                'regex:/^[0-9]{16}$/',
                Rule::unique('audition_participants', 'nik')
                    ->where('event_id', $event->id)
                    ->whereNull('deleted_at'),
            ],
            'birth_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) use ($event) {
                    if ($event->min_birth_date && $value < $event->min_birth_date->toDateString()) {
                        $fail("Tanggal lahir melebihi batas usia maksimal kompetisi (cut-off: {$event->min_birth_date->format('d/m/Y')}).");
                    }
                    if ($event->max_birth_date && $value > $event->max_birth_date->toDateString()) {
                        $fail("Tanggal lahir belum memenuhi batas usia minimal kompetisi (cut-off: {$event->max_birth_date->format('d/m/Y')}).");
                    }
                },
            ],
            'gender' => ['required', 'in:male,female'],
            'event_session_id' => [
                'nullable',
                Rule::exists('event_sessions', 'id')->where('event_id', $event->id),
            ],

            // Kontak Darurat & Legal
            'emergency_contact_name' => ['required', 'string', 'max:150'],
            'emergency_contact_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s\-()]{8,30}$/'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'medical_notes' => ['nullable', 'string', 'max:1000'],
            'liability_waiver' => ['accepted'],

            // Berkas Unggahan
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:3072'],
            'birth_certificate' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:4096'],
            'family_card' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:4096'],
            'additional_doc' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:4096'],

            // Dynamic Custom Answers
            'custom_answers' => ['nullable', 'array'],
        ];

        // Conditional Rules Berdasarkan Form Presets Event
        if (! empty($presets['position'])) {
            $rules['preferred_position'] = ['required', 'string', 'max:32'];
            $rules['secondary_position'] = ['nullable', 'string', 'max:32'];
        } else {
            $rules['preferred_position'] = ['nullable', 'string', 'max:32'];
            $rules['secondary_position'] = ['nullable', 'string', 'max:32'];
        }

        if (! empty($presets['foot'])) {
            $rules['dominant_foot'] = ['required', 'in:right,left,both'];
        } else {
            $rules['dominant_foot'] = ['nullable', 'in:right,left,both'];
        }

        if (! empty($presets['body_measurements'])) {
            $rules['height_cm'] = ['required', 'integer', 'between:80,230'];
            $rules['weight_kg'] = ['required', 'integer', 'between:20,150'];
        } else {
            $rules['height_cm'] = ['nullable', 'integer', 'between:80,230'];
            $rules['weight_kg'] = ['nullable', 'integer', 'between:20,150'];
        }

        if (! empty($presets['team_origin'])) {
            $rules['current_club_ssb'] = ['required', 'string', 'max:150'];
        } else {
            $rules['current_club_ssb'] = ['nullable', 'string', 'max:150'];
        }

        if (! empty($presets['experience'])) {
            $rules['playing_experience'] = ['nullable', 'string', 'max:2000'];
        }

        if (! empty($presets['jersey'])) {
            $rules['jersey_size'] = ['required', 'in:S,M,L,XL,XXL'];
        } else {
            $rules['jersey_size'] = ['nullable', 'in:S,M,L,XL,XXL'];
        }

        // Data Wali jika belum login
        if (! $isLoggedIn) {
            $rules['guardian_name'] = ['required', 'string', 'max:150'];
            $rules['guardian_email'] = ['required', 'string', 'email:rfc', 'max:160'];
            $rules['guardian_phone'] = ['required', 'string', 'max:25', 'regex:/^[0-9+\s\-()]{8,25}$/'];
            $rules['guardian_password'] = ['required', 'string', 'min:8'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap atlet wajib diisi.',
            'nik.required' => 'NIK 16 digit atlet wajib diisi.',
            'nik.size' => 'NIK harus tepat berjumlah 16 digit angka.',
            'nik.regex' => 'Format NIK hanya boleh berisi angka.',
            'nik.unique' => 'NIK ini telah terdaftar pada event ini.',
            'birth_date.required' => 'Tanggal lahir atlet wajib diisi.',
            'gender.required' => 'Jenis kelamin atlet wajib dipilih.',
            'preferred_position.required' => 'Posisi utama bermain wajib dipilih.',
            'dominant_foot.required' => 'Kaki dominan wajib dipilih.',
            'height_cm.required' => 'Tinggi badan wajib diisi.',
            'weight_kg.required' => 'Berat badan wajib diisi.',
            'current_club_ssb.required' => 'Asal SSB / Klub / Sekolah wajib diisi.',
            'jersey_size.required' => 'Ukuran jersey / rompi wajib dipilih.',
            'emergency_contact_name.required' => 'Nama kontak darurat kedua wajib diisi.',
            'emergency_contact_phone.required' => 'Nomor WhatsApp kontak darurat kedua wajib diisi.',
            'liability_waiver.accepted' => 'Anda wajib menyetujui pernyataan bebas tanggung jawab medis dan tata tertib audisi.',
            'photo.required' => 'Pas foto resmi atlet wajib diunggah.',
            'photo.image' => 'Berkas pas foto harus berformat gambar (JPG/PNG).',
            'birth_certificate.required' => 'Scan Akta Kelahiran wajib diunggah.',
            'family_card.required' => 'Scan Kartu Keluarga (KK) wajib diunggah.',
            'guardian_name.required' => 'Nama lengkap orang tua / wali wajib diisi.',
            'guardian_email.required' => 'Alamat email orang tua / wali wajib diisi.',
            'guardian_phone.required' => 'Nomor WhatsApp orang tua / wali wajib diisi.',
            'guardian_password.required' => 'Kata sandi akun portal wajib diisi.',
            'guardian_password.min' => 'Kata sandi minimal berjumlah 8 karakter.',
        ];
    }
}
