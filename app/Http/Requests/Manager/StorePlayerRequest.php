<?php

namespace App\Http\Requests\Manager;

use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');

        return $team && ($this->user()?->can('create', [Player::class, $team]) ?? false);
    }

    public function rules(): array
    {
        /** @var Team|null $team */
        $team = $this->route('team');

        $rules = [
            'full_name' => ['required', 'string', 'min:3', 'max:100'],
            'jersey_name' => ['nullable', 'string', 'max:50'],
            'jersey_number' => [
                'required',
                'integer',
                'min:1',
                'max:99',
                $team
                    ? Rule::unique('players', 'jersey_number')
                        ->where('team_id', $team->id)
                        ->whereNull('deleted_at')
                    : 'integer',
            ],
            'position' => ['required', Rule::in(array_keys(Player::POSITIONS))],
            'dominant_foot' => ['required', Rule::in(['Right', 'Left', 'Both'])],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['required', 'date'],
            'nik' => [
                'required',
                'string',
                'regex:/^\d{16}$/',
                $team
                    ? Rule::unique('players', 'nik')
                        ->where('event_id', $team->event_id)
                        ->whereNull('deleted_at')
                    : 'string',
            ],
            'nisn' => ['nullable', 'string', 'max:20'],
            'height_cm' => ['nullable', 'integer', 'min:90', 'max:230'],
            'weight_kg' => ['nullable', 'integer', 'min:15', 'max:150'],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'documents' => ['nullable', 'array'],
        ];

        foreach (array_keys(Event::DOCUMENT_TYPES) as $docType) {
            $rules["documents.{$docType}"] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Team|null $team */
                $team = $this->route('team');
                if (! $team) {
                    return;
                }

                $event = $team->event;

                if ($event && $team->players()->count() >= $event->max_players_per_team) {
                    $validator->errors()->add(
                        'player_quota',
                        "Jumlah pemain pada tim Anda telah mencapai batas kuota maksimal kompetisi ini ({$event->max_players_per_team} pemain)."
                    );
                }

                $this->validateAgeCutOff($validator, $event);
            },
        ];
    }

    protected function validateAgeCutOff(Validator $validator, ?Event $event): void
    {
        if (! $event || ! $this->filled('birth_date')) {
            return;
        }

        try {
            $birth = Carbon::parse($this->input('birth_date'))->startOfDay();
        } catch (\Throwable) {
            return;
        }

        if ($event->min_birth_date && $birth->lt($event->min_birth_date->copy()->startOfDay())) {
            $validator->errors()->add(
                'birth_date',
                'Tanggal lahir pemain tidak memenuhi syarat batas usia pada kategori kompetisi ini.'
            );

            return;
        }

        if ($event->max_birth_date && $birth->gt($event->max_birth_date->copy()->startOfDay())) {
            $validator->errors()->add(
                'birth_date',
                'Tanggal lahir pemain tidak memenuhi syarat batas usia pada kategori kompetisi ini.'
            );
        }
    }

    public function messages(): array
    {
        $messages = [
            'full_name.required' => 'Nama lengkap pemain sesuai Akta Kelahiran wajib diisi.',
            'full_name.min' => 'Nama lengkap pemain minimal 3 karakter.',
            'jersey_number.required' => 'Nomor punggung wajib diisi antara angka 1 hingga 99.',
            'jersey_number.integer' => 'Nomor punggung wajib diisi antara angka 1 hingga 99.',
            'jersey_number.min' => 'Nomor punggung wajib diisi antara angka 1 hingga 99.',
            'jersey_number.max' => 'Nomor punggung wajib diisi antara angka 1 hingga 99.',
            'jersey_number.unique' => 'Nomor punggung ini sudah digunakan oleh pemain lain di tim Anda.',
            'position.required' => 'Posisi bermain wajib dipilih.',
            'position.in' => 'Posisi bermain yang dipilih tidak valid.',
            'birth_date.required' => 'Tanggal lahir pemain wajib diisi.',
            'birth_date.date' => 'Format tanggal lahir pemain tidak valid.',
            'nik.required' => 'Nomor Induk Kependudukan (NIK) wajib terdiri dari 16 digit angka.',
            'nik.regex' => 'Nomor Induk Kependudukan (NIK) wajib terdiri dari 16 digit angka.',
            'nik.unique' => 'Pemain dengan NIK ini sudah terdaftar di tim lain pada kompetisi yang sama.',
            'photo.required' => 'Pas foto resmi pemain wajib diunggah (format JPG/PNG, maks 2 MB).',
            'photo.mimes' => 'Pas foto resmi pemain wajib diunggah (format JPG/PNG, maks 2 MB).',
            'photo.max' => 'Pas foto resmi pemain wajib diunggah (format JPG/PNG, maks 2 MB).',
        ];

        foreach (Event::DOCUMENT_TYPES as $docType => $label) {
            $messages["documents.{$docType}.mimes"] = "Berkas {$label} harus berformat JPG, PNG, atau PDF dengan ukuran maksimal 2 MB.";
            $messages["documents.{$docType}.max"] = "Berkas {$label} harus berformat JPG, PNG, atau PDF dengan ukuran maksimal 2 MB.";
        }

        return $messages;
    }
}

