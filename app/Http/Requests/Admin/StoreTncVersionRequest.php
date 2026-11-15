<?php

namespace App\Http\Requests\Admin;

use App\Models\TncVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTncVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'code' => ['nullable', 'string', 'max:64'],
            'scope' => ['required', Rule::in(TncVersion::SCOPES)],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:50000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul dokumen Syarat & Ketentuan wajib diisi.',
            'title.min' => 'Judul dokumen minimal 3 karakter.',
            'title.max' => 'Judul dokumen maksimal 180 karakter.',
            'code.max' => 'Kode dokumen maksimal 64 karakter.',
            'scope.required' => 'Cakupan jenis event wajib dipilih.',
            'scope.in' => 'Cakupan jenis event tidak valid.',
            'summary.max' => 'Ringkasan teks checkbox maksimal 500 karakter.',
            'content.required' => 'Isi lengkap dokumen Syarat & Ketentuan wajib diisi.',
            'content.max' => 'Isi dokumen maksimal 50.000 karakter.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $plain = trim(strip_tags((string) $this->input('content', '')));
                if ($plain === '') {
                    $validator->errors()->add('content', 'Isi dokumen Syarat & Ketentuan tidak boleh kosong.');
                }
            },
        ];
    }
}

