<?php

namespace App\Http\Requests\Public;

use App\Models\AuditionParticipant;
use Illuminate\Foundation\Http\FormRequest;

class ReuploadAuditionDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AuditionParticipant|null $participant */
        $participant = $this->route('participant');

        return $participant instanceof AuditionParticipant
            && $participant->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            'birth_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'family_card' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'additional_doc' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        //
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->hasFile('birth_certificate')
                && ! $this->hasFile('family_card')
                && ! $this->hasFile('additional_doc')) {
                $validator->errors()->add('documents', 'Minimal satu dokumen wajib diunggah.');
            }
        });
    }
}
