<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentGatewaySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method_mode' => ['required', 'in:manual,midtrans,xendit,gateway'],
            'payment_gateway_default' => ['nullable', 'in:midtrans,xendit'],
            'payment_enabled_providers' => ['nullable', 'array'],
            'payment_enabled_providers.*' => ['in:midtrans,xendit'],
            'manual_bank_accounts' => ['nullable', 'array'],
            'manual_bank_accounts.*.bank_name' => ['required_if:payment_method_mode,manual', 'string', 'max:100'],
            'manual_bank_accounts.*.account_number' => ['required_if:payment_method_mode,manual', 'string', 'max:50'],
            'manual_bank_accounts.*.account_holder' => ['required_if:payment_method_mode,manual', 'string', 'max:150'],
            'manual_bank_accounts.*.notes' => ['nullable', 'string', 'max:150'],
            'midtrans_server_key' => ['nullable', 'string', 'max:255'],
            'midtrans_client_key' => ['nullable', 'string', 'max:255'],
            'midtrans_is_production' => ['nullable', 'boolean'],
            'xendit_secret_key' => ['nullable', 'string', 'max:255'],
            'xendit_callback_token' => ['nullable', 'string', 'max:255'],
            'fee_borne_by' => ['nullable', 'in:team,organizer'],
            'midtrans_va_fee' => ['nullable', 'numeric', 'min:0'],
            'midtrans_qris_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'midtrans_ewallet_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'midtrans_cc_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'midtrans_cc_fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'midtrans_channels_mode' => ['nullable', 'in:all,custom'],
            'midtrans_active_channels' => ['nullable', 'array'],
            'midtrans_active_channels.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method_mode.required' => 'Pilih salah satu metode pembayaran aktif.',
            'manual_bank_accounts.*.bank_name.required_if' => 'Nama bank penerima wajib diisi.',
            'manual_bank_accounts.*.account_number.required_if' => 'Nomor rekening wajib diisi.',
            'manual_bank_accounts.*.account_holder.required_if' => 'Nama pemilik rekening wajib diisi.',
        ];
    }
}

