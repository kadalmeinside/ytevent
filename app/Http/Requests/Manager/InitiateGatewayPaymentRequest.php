<?php

namespace App\Http\Requests\Manager;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateGatewayPaymentRequest extends FormRequest
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
            'provider' => ['required', Rule::in(['midtrans', 'xendit'])],
            'payment_channel' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => 'Penyedia pembayaran otomatis wajib dipilih.',
            'provider.in' => 'Penyedia pembayaran yang dipilih tidak didukung.',
            'payment_channel.required' => 'Saluran pembayaran (Virtual Account / QRIS / E-Wallet) wajib dipilih.',
        ];
    }
}

