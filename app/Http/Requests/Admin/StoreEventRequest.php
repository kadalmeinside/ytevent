<?php

namespace App\Http\Requests\Admin;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Event::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'short_description' => ['nullable', 'string', 'max:1500'],
            'venue_name' => ['required', 'string', 'min:3', 'max:160'],
            'city' => ['required', 'string', 'min:2', 'max:120'],
            'format' => ['required', Rule::in(array_keys(Event::FORMATS))],
            'age_category' => ['required', 'string', 'max:32'],
            'min_birth_date' => ['nullable', 'date'],
            'max_birth_date' => ['nullable', 'date', 'after_or_equal:min_birth_date'],
            'min_players_per_team' => ['required', 'integer', 'min:5', 'max:50'],
            'max_players_per_team' => ['required', 'integer', 'gte:min_players_per_team', 'max:60'],
            'max_officials_per_team' => ['required', 'integer', 'min:1', 'max:15'],
            'required_documents' => ['required', 'array', 'min:1'],
            'required_documents.*' => ['required', Rule::in(array_keys(Event::DOCUMENT_TYPES))],
            'max_teams' => ['required', 'integer', 'min:2', 'max:512'],
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
            'banner_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'regulation_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
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

