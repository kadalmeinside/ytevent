<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class QuotaExpirationService
{
    /**
     * Menyapu pembayaran unpaid/rejected yang telah melewati expires_at pada event yang SUDAH dikunci (lockForUpdate).
     */
    public function sweepExpiredForEvent(Event $lockedEvent): int
    {
        $now = now();

        $expiredPayments = Payment::query()
            ->where('event_id', $lockedEvent->id)
            ->whereIn('status', ['unpaid', 'rejected'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->lockForUpdate()
            ->get();

        $count = 0;

        foreach ($expiredPayments as $payment) {
            $payment->update(['status' => 'expired']);

            $team = $payment->team()->lockForUpdate()->first();
            if ($team && $team->status === 'pending_payment') {
                $hasActiveOrWaitingPayment = $team->payments()
                    ->whereIn('status', ['paid', 'waiting_verification'])
                    ->orWhere(function ($q) use ($now) {
                        $q->whereIn('status', ['unpaid', 'rejected'])
                            ->where('expires_at', '>', $now);
                    })
                    ->exists();

                if (! $hasActiveOrWaitingPayment) {
                    $team->update(['status' => 'expired']);
                }
            }

            $count++;
        }

        // Juga pastikan tim pending_payment yang slot_expires_at sudah lewat tanpa waiting_verification ditandai expired
        $staleTeams = $lockedEvent->teams()
            ->where('status', 'pending_payment')
            ->whereNotNull('slot_expires_at')
            ->where('slot_expires_at', '<=', $now)
            ->whereDoesntHave('payments', function ($q) {
                $q->whereIn('status', ['paid', 'waiting_verification']);
            })
            ->lockForUpdate()
            ->get();

        foreach ($staleTeams as $staleTeam) {
            $staleTeam->update(['status' => 'expired']);
            $staleTeam->payments()
                ->whereIn('status', ['unpaid', 'rejected'])
                ->update(['status' => 'expired']);
            $count++;
        }

        $lockedEvent->syncQuotaStatus();

        return $count;
    }

    /**
     * Menyapu seluruh event yang memiliki tagihan kedaluwarsa (dipanggil oleh Scheduler).
     */
    public function sweepAllEvents(): int
    {
        $now = now();

        $eventIdsFromPayments = Payment::query()
            ->whereIn('status', ['unpaid', 'rejected'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->pluck('event_id');

        $eventIdsFromTeams = DB::table('teams')
            ->whereNull('deleted_at')
            ->where('status', 'pending_payment')
            ->whereNotNull('slot_expires_at')
            ->where('slot_expires_at', '<=', $now)
            ->pluck('event_id');

        $eventIds = $eventIdsFromPayments->merge($eventIdsFromTeams)->unique()->values();

        $totalExpired = 0;

        foreach ($eventIds as $eventId) {
            $totalExpired += DB::transaction(function () use ($eventId) {
                $lockedEvent = Event::query()->whereKey($eventId)->lockForUpdate()->first();
                if (! $lockedEvent) {
                    return 0;
                }

                return $this->sweepExpiredForEvent($lockedEvent);
            });
        }

        return $totalExpired;
    }
}

