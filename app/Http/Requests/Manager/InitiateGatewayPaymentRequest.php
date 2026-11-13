<?php

namespace App\Http\Requests\Manager;

use App\Models\Payment;
use App\Services\PaymentService;
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
        $provider = (string) $this->input('provider', '');
        $allowedChannels = PaymentService::allowedChannelsForProvider($provider);

        return [
            'provider' => ['required', Rule::in(['midtrans', 'xendit'])],
            'payment_channel' => array_filter([
                'nullable',
                'string',
                'max:64',
                ! empty($allowedChannels) ? Rule::in($allowedChannels) : null,
            ]),
            'force_regenerate' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => 'Penyedia pembayaran otomatis wajib dipilih.',
            'provider.in' => 'Penyedia pembayaran yang dipilih tidak didukung.',
            'payment_channel.required' => 'Saluran pembayaran (Virtual Account / QRIS / E-Wallet) wajib dipilih.',
            'payment_channel.in' => 'Saluran pembayaran yang dipilih tidak valid atau tidak didukung oleh penyedia pembayaran.',
        ];
    }
}

