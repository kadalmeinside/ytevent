<?php

namespace App\Http\Requests\Admin;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Event::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('event_type')) {
            $this->merge(['event_type' => 'tournament']);
        }
        if (! $this->has('participant_type')) {
            $this->merge(['participant_type' => 'team']);
        }
        if (! $this->has('quota_type')) {
            $this->merge(['quota_type' => 'fixed']);
        }
        if (! $this->has('form_presets') || empty($this->input('form_presets'))) {
            $this->merge(['form_presets' => Event::DEFAULT_FORM_PRESETS]);
        }
        if (! $this->has('match_format')) {
            $this->merge(['match_format' => '11_vs_11']);
        }
        if (! $this->has('starting_players_count')) {
            $format = $this->input('match_format', '11_vs_11');
            $this->merge([
                'starting_players_count' => Event::MATCH_FORMATS[$format]['starting_count'] ?? 11,
            ]);
        }
        if (! $this->has('dsp_cutoff_minutes')) {
            $this->merge(['dsp_cutoff_minutes' => 30]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'short_description' => ['nullable', 'string', 'max:20000'],
            'venue_name' => ['required', 'string', 'min:3', 'max:160'],
            'venue_maps_url' => ['nullable', 'url', 'regex:/^https?:\/\//i', 'max:500'],
            'city' => ['required', 'string', 'min:2', 'max:120'],
            'event_type' => ['sometimes', 'required', Rule::in(array_keys(Event::EVENT_TYPES))],
            'participant_type' => ['sometimes', 'required', Rule::in(array_keys(Event::PARTICIPANT_TYPES))],
            'quota_type' => ['sometimes', 'required', Rule::in(array_keys(Event::QUOTA_TYPES))],
            'max_participants' => [
                Rule::requiredIf(fn () => ($this->input('quota_type', 'fixed') === 'fixed') && ($this->input('participant_type') === 'individual')),
                'nullable',
                'integer',
                'min:2',
                'max:10000',
            ],
            'position_quota_caps' => ['nullable', 'array'],
            'form_presets' => ['nullable', 'array'],
            'custom_fields_schema' => ['nullable', 'array'],
            'format' => ['required', Rule::in(array_keys(Event::FORMATS))],
            'match_format' => ['sometimes', 'required', Rule::in(array_keys(Event::MATCH_FORMATS))],
            'starting_players_count' => ['sometimes', 'required', 'integer', 'min:3', 'max:11'],
            'dsp_cutoff_minutes' => ['sometimes', 'required', 'integer', 'min:0', 'max:180'],
            'age_category' => ['required', 'string', 'max:32'],
            'min_birth_date' => ['nullable', 'date'],
            'max_birth_date' => ['nullable', 'date', 'after_or_equal:min_birth_date'],
            'min_players_per_team' => ['required', 'integer', 'min:5', 'max:50'],
            'max_players_per_team' => ['required', 'integer', 'gte:min_players_per_team', 'max:60'],
            'min_gk_per_team' => ['sometimes', 'required', 'integer', Rule::in([1, 2])],
            'max_officials_per_team' => ['required', 'integer', 'min:1', 'max:15'],
            'show_coach_license' => ['sometimes', 'required', 'boolean'],
            'require_coach_license' => ['sometimes', 'required', 'boolean'],
            'required_documents' => ['required', 'array', 'min:1'],
            'required_documents.*' => ['required', Rule::in(array_keys(Event::DOCUMENT_TYPES))],
            'max_teams' => [
                Rule::requiredIf(fn () => ($this->input('quota_type', 'fixed') === 'fixed') && ($this->input('participant_type', 'team') === 'team')),
                'nullable',
                'integer',
                'min:2',
                'max:512',
            ],
            'registration_fee' => ['required', 'numeric', 'min:0'],
            'prize_info' => ['nullable', 'string', 'max:255'],
            'lock_roster_until_paid' => ['required', 'boolean'],
            'payment_deadline_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'reupload_grace_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'registration_open_at' => ['nullable', 'date'],
            'registration_close_at' => ['nullable', 'date', 'after_or_equal:registration_open_at'],
            'match_start_date' => ['nullable', 'date'],
            'match_end_date' => ['nullable', 'date', 'after_or_equal:match_start_date'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'contact_whatsapp' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(array_keys(Event::STATUSES))],
            'banner_file' => ['nullable', File::image()->max(2048), 'mimes:png,jpg,jpeg'],
            'regulation_pdf' => ['nullable', File::types(['pdf'])->max(5120)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kompetisi wajib diisi.',
            'name.min' => 'Nama kompetisi minimal terdiri dari 3 karakter.',
            'venue_name.required' => 'Lokasi atau nama stadion utama wajib diisi.',
            'city.required' => 'Kota penyelenggaraan wajib diisi.',
            'format.required' => 'Format kompetisi wajib dipilih.',
            'age_category.required' => 'Label kategori usia wajib diisi (contoh: U-15, Senior).',
            'max_birth_date.after_or_equal' => 'Batas tanggal lahir maksimal tidak boleh lebih awal dari tanggal lahir minimal.',
            'min_players_per_team.required' => 'Batas minimal pemain per tim wajib diisi.',
            'max_players_per_team.required' => 'Batas maksimal pemain per tim wajib diisi.',
            'max_players_per_team.gte' => 'Batas maksimal pemain tidak boleh lebih kecil dari batas minimal pemain.',
            'min_gk_per_team.required' => 'Syarat minimal Penjaga Gawang (GK) per tim wajib dipilih.',
            'min_gk_per_team.in' => 'Syarat minimal Penjaga Gawang (GK) per tim harus bernilai 1 atau 2 kiper.',
            'required_documents.required' => 'Pilih minimal satu jenis dokumen kelengkapan wajib untuk pemain.',
            'required_documents.min' => 'Pilih minimal satu jenis dokumen kelengkapan wajib untuk pemain.',
            'max_teams.required' => 'Kuota maksimal tim wajib diisi.',
            'max_teams.min' => 'Kuota maksimal tim wajib berupa angka minimal 2 tim.',
            'registration_fee.required' => 'Biaya pendaftaran wajib diisi (isi 0 jika gratis).',
            'registration_fee.min' => 'Biaya pendaftaran tidak boleh bernilai negatif.',
            'payment_deadline_hours.required' => 'Batas waktu pembayaran reservasi slot wajib diisi (dalam jam).',
            'registration_close_at.after_or_equal' => 'Tanggal tutup pendaftaran harus setelah atau sama dengan tanggal buka pendaftaran.',
            'match_end_date.after_or_equal' => 'Tanggal selesai pertandingan harus setelah atau sama dengan tanggal mulai.',
            'banner_file.mimes' => 'Poster/banner event harus berformat PNG atau JPG.',
            'banner_file.max' => 'Ukuran poster/banner tidak boleh melebihi 2 MB.',
            'regulation_pdf.mimes' => 'Berkas regulasi teknis harus berformat PDF.',
            'regulation_pdf.max' => 'Ukuran berkas PDF regulasi maksimal 5 MB.',
        ];
    }
}
