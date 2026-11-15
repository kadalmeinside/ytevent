<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'min:3', 'max:160'],
            'app_tagline' => ['nullable', 'string', 'max:255'],
            'footer_copyright' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_whatsapp' => ['nullable', 'string', 'max:30'],
            'header_logo_style' => ['nullable', 'string', 'in:logo_only,logo_and_text'],
            'ga4_measurement_id' => ['nullable', 'string', 'max:40', 'regex:/^(G-[A-Z0-9]+)?$/i'],
            'plausible_domain' => ['nullable', 'string', 'max:160'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,svg', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'app_name.required' => 'Nama situs/aplikasi wajib diisi.',
            'ga4_measurement_id.regex' => 'Format Measurement ID Google Analytics 4 harus diawali G- (contoh: G-XXXXXXXXXX).',
            'logo.max' => 'Ukuran berkas logo maksimal 2 MB.',
            'favicon.max' => 'Ukuran berkas favicon maksimal 1 MB.',
        ];
    }
}

