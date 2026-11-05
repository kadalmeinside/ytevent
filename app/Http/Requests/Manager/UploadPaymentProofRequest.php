<?php

namespace App\Http\Requests\Manager;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Payment|null $payment */
        $payment = $this->route('payment');

        return $payment && ($this->user()?->can('pay', $payment) ?? false);
    }

    public function rules(): array
    {
        return [
            'sender_bank_name' => ['required', 'string', 'min:2', 'max:100'],
            'sender_account_holder' => ['required', 'string', 'min:3', 'max:120'],
            'sender_account_number' => ['nullable', 'string', 'max:64'],
            'transfer_date' => ['required', 'date'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'sender_bank_name.required' => 'Nama bank pengirim wajib diisi.',
            'sender_account_holder.required' => 'Nama pemilik rekening pengirim wajib diisi.',
            'transfer_date.required' => 'Tanggal transfer pembayaran wajib diisi.',
            'transfer_date.date' => 'Format tanggal transfer tidak valid.',
            'proof_file.required' => 'Bukti transfer wajib diunggah dalam format JPG, PNG, atau PDF (maksimal 2 MB).',
            'proof_file.mimes' => 'Bukti transfer wajib diunggah dalam format JPG, PNG, atau PDF (maksimal 2 MB).',
            'proof_file.max' => 'Bukti transfer wajib diunggah dalam format JPG, PNG, atau PDF (maksimal 2 MB).',
        ];
    }
}

