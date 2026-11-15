<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Team;
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

        $count = $expiredPayments->count();

        if ($count > 0) {
            Payment::query()
                ->whereIn('id', $expiredPayments->pluck('id'))
                ->update(['status' => 'expired']);

            $candidateTeamIds = $expiredPayments->pluck('team_id')->filter()->unique()->values();

            if ($candidateTeamIds->isNotEmpty()) {
                $pendingTeams = Team::query()
                    ->whereIn('id', $candidateTeamIds)
                    ->where('status', 'pending_payment')
                    ->lockForUpdate()
                    ->pluck('id');

                if ($pendingTeams->isNotEmpty()) {
                    $protectedTeamIds = Payment::query()
                        ->whereIn('team_id', $pendingTeams)
                        ->where(function ($query) use ($now) {
                            $query->whereIn('status', ['paid', 'waiting_verification'])
                                ->orWhere(function ($q) use ($now) {
                                    $q->whereIn('status', ['unpaid', 'rejected'])
                                        ->where('expires_at', '>', $now);
                                });
                        })
                        ->distinct()
                        ->pluck('team_id');

                    $teamsToExpire = $pendingTeams->diff($protectedTeamIds)->values();
                    if ($teamsToExpire->isNotEmpty()) {
                        Team::query()
                            ->whereIn('id', $teamsToExpire)
                            ->update(['status' => 'expired']);
                    }
                }
            }
        }

        // Juga pastikan tim pending_payment yang slot_expires_at sudah lewat tanpa waiting_verification ditandai expired
        $staleTeams = $lockedEvent->teams()
            ->with(['event', 'user'])
            ->where('status', 'pending_payment')
            ->whereNotNull('slot_expires_at')
            ->where('slot_expires_at', '<=', $now)
            ->whereDoesntHave('payments', function ($q) {
                $q->whereIn('status', ['paid', 'waiting_verification']);
            })
            ->lockForUpdate()
            ->get();

        if ($staleTeams->isNotEmpty()) {
            $staleTeamIds = $staleTeams->pluck('id');

            Team::query()
                ->whereIn('id', $staleTeamIds)
                ->update(['status' => 'expired']);

            Payment::query()
                ->whereIn('team_id', $staleTeamIds)
                ->whereIn('status', ['unpaid', 'rejected'])
                ->update(['status' => 'expired']);

            $emailService = app(EmailNotificationService::class);
            foreach ($staleTeams as $staleTeam) {
                $staleTeam->status = 'expired';
                $count++;
                $emailService->sendSlotExpired($staleTeam);
            }
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

