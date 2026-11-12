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

        if ($payment->team_id && (int) $payment->team?->user_id === (int) $user->id) {
            return true;
        }

        if ($payment->audition_participant_id && (int) $payment->auditionParticipant?->user_id === (int) $user->id) {
            return true;
        }

        return false;
    }

    public function pay(User $user, Payment $payment): bool
    {
        $isOwner = false;
        if ($payment->team_id && (int) $payment->team?->user_id === (int) $user->id) {
            $isOwner = true;
        } elseif ($payment->audition_participant_id && (int) $payment->auditionParticipant?->user_id === (int) $user->id) {
            $isOwner = true;
        }

        return $isOwner && in_array($payment->status, ['unpaid', 'rejected'], true);
    }

    public function verifyManual(User $user, Payment $payment): bool
    {
        return $user->can('payments.verify_manual');
    }
}

