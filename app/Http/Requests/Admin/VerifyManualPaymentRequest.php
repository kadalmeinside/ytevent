<?php

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Payment|null $payment */
        $payment = $this->route('payment');

        return $payment && ($this->user()?->can('verifyManual', $payment) ?? false);
    }

    public function rules(): array
    {
        $isReject = $this->input('action') === 'reject';

        return [
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'rejection_reason' => $isReject
                ? ['required', 'string', 'min:10', 'max:1000']
                : ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Keputusan verifikasi pembayaran wajib dipilih.',
            'rejection_reason.required' => 'Alasan penolakan wajib diisi secara jelas (minimal 10 karakter) sebagai petunjuk perbaikan bagi Manajer Tim.',
            'rejection_reason.min' => 'Alasan penolakan wajib diisi secara jelas (minimal 10 karakter) sebagai petunjuk perbaikan bagi Manajer Tim.',
        ];
    }
}

