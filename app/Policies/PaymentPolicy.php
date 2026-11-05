<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view_all');
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->can('payments.view_all')) {
            return true;
        }

        return $user->can('manager.payment.pay_own')
            && (int) $payment->team?->user_id === (int) $user->id;
    }

    public function pay(User $user, Payment $payment): bool
    {
        return $user->can('manager.payment.pay_own')
            && (int) $payment->team?->user_id === (int) $user->id
            && in_array($payment->status, ['unpaid', 'rejected'], true);
    }

    public function verifyManual(User $user, Payment $payment): bool
    {
        return $user->can('payments.verify_manual');
    }
}

