<?php

namespace App\Http\Requests\Manager;

use App\Models\Event;
use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadPlayerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Player|null $player */
        $player = $this->route('player');

        return $player && ($this->user()?->can('update', $player) ?? false);
    }

    public function rules(): array
    {
        /** @var Player|null $player */
        $player = $this->route('player');
        $docType = (string) $this->input('document_type', '');
        $hasExisting = $player && $player->documents()->where('document_type', $docType)->exists();

        return [
            'document_type' => ['required', Rule::in(array_keys(Event::DOCUMENT_TYPES))],
            'document_number' => ['nullable', 'string', 'max:100'],
            'file' => [$hasExisting ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        $docType = (string) $this->input('document_type', '');
        $docLabel = Event::DOCUMENT_TYPES[$docType] ?? 'Dokumen Pemain';

        return [
            'document_type.required' => 'Jenis dokumen wajib dipilih.',
            'document_type.in' => 'Jenis dokumen yang dipilih tidak valid.',
            'file.required' => "Berkas {$docLabel} wajib diunggah.",
            'file.mimes' => "Berkas {$docLabel} harus berformat JPG, PNG, atau PDF dengan ukuran maksimal 2 MB.",
            'file.max' => "Berkas {$docLabel} harus berformat JPG, PNG, atau PDF dengan ukuran maksimal 2 MB.",
        ];
    }
}

