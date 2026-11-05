<?php

namespace App\Services;

use App\Mail\PaymentConfirmedMail;
use App\Mail\PaymentProofRejectedMail;
use App\Mail\SlotExpiredMail;
use App\Mail\TeamRegisteredMail;
use App\Models\EmailNotificationLog;
use App\Models\Payment;
use App\Models\Team;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailNotificationService
{
    /**
     * Kirim email pendaftaran tim baru (Tagihan & Reservasi Slot).
     */
    public function sendTeamRegistered(Team $team, Payment $payment): ?EmailNotificationLog
    {
        $recipientEmail = $team->manager_email ?: $team->user?->email;
        if (!$recipientEmail) return null;

        $mailable = new TeamRegisteredMail($team, $payment);
        return $this->sendAndLog($recipientEmail, $mailable, $team->event_id, $team->id);
    }

    /**
     * Kirim email konfirmasi pembayaran lunas & aktivasi tim.
     */
    public function sendPaymentConfirmed(Team $team, Payment $payment): ?EmailNotificationLog
    {
        $recipientEmail = $team->manager_email ?: $team->user?->email;
        if (!$recipientEmail) return null;

        $mailable = new PaymentConfirmedMail($team, $payment);
        return $this->sendAndLog($recipientEmail, $mailable, $team->event_id, $team->id);
    }

    /**
     * Kirim email penolakan bukti transfer manual dengan alasan.
     */
    public function sendPaymentRejected(Team $team, Payment $payment, string $reason): ?EmailNotificationLog
    {
        $recipientEmail = $team->manager_email ?: $team->user?->email;
        if (!$recipientEmail) return null;

        $mailable = new PaymentProofRejectedMail($team, $payment, $reason);
        return $this->sendAndLog($recipientEmail, $mailable, $team->event_id, $team->id);
    }

    /**
     * Kirim email slot kedaluwarsa.
     */
    public function sendSlotExpired(Team $team): ?EmailNotificationLog
    {
        $recipientEmail = $team->manager_email ?: $team->user?->email;
        if (!$recipientEmail) return null;

        $mailable = new SlotExpiredMail($team);
        return $this->sendAndLog($recipientEmail, $mailable, $team->event_id, $team->id);
    }

    /**
     * Eksekusi pengiriman email dan catat ke email_notification_logs dengan fail-safe.
     */
    protected function sendAndLog(
        string $recipientEmail,
        Mailable $mailable,
        ?int $eventId = null,
        ?int $teamId = null
    ): EmailNotificationLog {
        $mailableClass = get_class($mailable);

        $log = EmailNotificationLog::create([
            'event_id' => $eventId,
            'team_id' => $teamId,
            'mailable_type' => class_basename($mailableClass),
            'recipient_email' => $recipientEmail,
            'status' => 'pending',
        ]);

        try {
            Mail::to($recipientEmail)->send($mailable);

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            report($e);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $log;
    }
}
