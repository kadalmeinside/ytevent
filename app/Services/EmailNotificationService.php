<?php

namespace App\Services;

use App\Jobs\SendLoggedEmailJob;
use App\Mail\AuditionPaymentConfirmedMail;
use App\Mail\PaymentConfirmedMail;
use App\Mail\PaymentProofRejectedMail;
use App\Mail\PaymentRefundedMail;
use App\Mail\ResetPasswordMail;
use App\Mail\RosterLockedMail;
use App\Mail\ScreeningResultMail;
use App\Mail\SlotExpiredMail;
use App\Mail\TeamRegisteredMail;
use App\Models\AuditionParticipant;
use App\Models\EmailNotificationLog;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Throwable;

class EmailNotificationService
{
    /**
     * Kirim email pendaftaran tim baru (Tagihan & Reservasi Slot).
     */
    public function sendTeamRegistered(Team $team, Payment $payment): ?EmailNotificationLog
    {
        return $this->queueForTeam($team, new TeamRegisteredMail($team, $payment));
    }

    /**
     * Kirim email konfirmasi pembayaran lunas & aktivasi tim.
     */
    public function sendPaymentConfirmed(Team $team, Payment $payment): ?EmailNotificationLog
    {
        return $this->queueForTeam($team, new PaymentConfirmedMail($team, $payment));
    }

    /**
     * Kirim email konfirmasi pembayaran lunas & aktivasi peserta audisi individu.
     */
    public function sendAuditionPaymentConfirmed(AuditionParticipant $participant, Payment $payment): ?EmailNotificationLog
    {
        $recipientEmail = $participant->user?->email;
        if (! $recipientEmail) {
            return null;
        }

        return $this->queueAndLog(
            recipientEmail: $recipientEmail,
            mailable: new AuditionPaymentConfirmedMail($participant, $payment),
            eventId: $participant->event_id,
            teamId: null,
        );
    }

    /**
     * Kirim email penolakan bukti transfer manual dengan alasan.
     */
    public function sendPaymentRejected(Team $team, Payment $payment, string $reason): ?EmailNotificationLog
    {
        return $this->queueForTeam($team, new PaymentProofRejectedMail($team, $payment, $reason));
    }

    /**
     * Kirim email slot kedaluwarsa.
     */
    public function sendSlotExpired(Team $team): ?EmailNotificationLog
    {
        return $this->queueForTeam($team, new SlotExpiredMail($team));
    }

    /**
     * Kirim rekapitulasi hasil screening (pemain sah vs perlu revisi) ke manajer tim.
     */
    public function sendScreeningResult(Team $team): ?EmailNotificationLog
    {
        return $this->queueForTeam($team, new ScreeningResultMail($team));
    }

    /**
     * Kirim notifikasi skuad terkunci resmi & dokumen akreditasi siap diunduh.
     */
    public function sendRosterLocked(Team $team): ?EmailNotificationLog
    {
        return $this->queueForTeam($team, new RosterLockedMail($team));
    }

    /**
     * Kirim notifikasi pengembalian dana (refund) telah diselesaikan Finance.
     */
    public function sendPaymentRefunded(Team $team, Payment $payment, string $refundNotes): ?EmailNotificationLog
    {
        return $this->queueForTeam($team, new PaymentRefundedMail($team, $payment, $refundNotes));
    }

    /**
     * Kirim email tautan atur ulang kata sandi (reset password).
     */
    public function sendPasswordReset(User $user, string $resetUrl): EmailNotificationLog
    {
        $team = Team::query()->where('user_id', $user->id)->latest('id')->first();

        return $this->queueAndLog(
            recipientEmail: $user->email,
            mailable: new ResetPasswordMail($user, $resetUrl),
            eventId: $team?->event_id,
            teamId: $team?->id
        );
    }

    /**
     * Kirim ulang email yang gagal. Mengembalikan log yang telah diperbarui.
     */
    public function retry(EmailNotificationLog $log): EmailNotificationLog
    {
        if (! $log->isRetryable()) {
            return $log;
        }

        $log->update([
            'status' => EmailNotificationLog::STATUS_QUEUED,
            'error_message' => null,
        ]);

        SendLoggedEmailJob::dispatch($log->id);

        return $log->fresh();
    }

    protected function queueForTeam(Team $team, Mailable $mailable): ?EmailNotificationLog
    {
        $recipientEmail = $team->manager_email ?: $team->user?->email;
        if (! $recipientEmail) {
            return null;
        }

        return $this->queueAndLog($recipientEmail, $mailable, $team->event_id, $team->id);
    }

    /**
     * Catat email ke email_notification_logs (status queued) lalu kirim melalui antrean latar belakang.
     * Bersifat fail-safe: kegagalan pencatatan/antrean tidak menggagalkan alur bisnis utama.
     */
    protected function queueAndLog(
        string $recipientEmail,
        Mailable $mailable,
        ?int $eventId = null,
        ?int $teamId = null
    ): EmailNotificationLog {
        $subject = null;
        try {
            $subject = $mailable->envelope()->subject ?? null;
        } catch (Throwable) {
            // Subjek bersifat informatif saja.
        }

        if (empty($subject)) {
            $subject = EmailNotificationLog::MAILABLE_LABELS[class_basename($mailable)] ?? class_basename($mailable);
        }

        $log = EmailNotificationLog::create([
            'event_id' => $eventId,
            'team_id' => $teamId,
            'mailable_type' => class_basename($mailable),
            'recipient_email' => $recipientEmail,
            'subject' => $subject,
            'payload' => static::signPayload(serialize($mailable)),
            'status' => EmailNotificationLog::STATUS_QUEUED,
        ]);

        try {
            SendLoggedEmailJob::dispatch($log->id);
        } catch (Throwable $e) {
            report($e);
            $log->update([
                'status' => EmailNotificationLog::STATUS_FAILED,
                'error_message' => 'Gagal memasukkan email ke antrean: ' . mb_substr($e->getMessage(), 0, 1900),
            ]);
        }

        return $log->fresh();
    }

    /**
     * Tanda-tangani payload mailable (HMAC) agar payload yang dimanipulasi di database tidak pernah di-unserialize.
     */
    public static function signPayload(string $serialized): string
    {
        return hash_hmac('sha256', $serialized, (string) config('app.key')) . '.' . $serialized;
    }

    /**
     * Verifikasi tanda tangan payload; mengembalikan string terserialisasi atau null jika tidak valid.
     */
    public static function verifyPayload(?string $signed): ?string
    {
        if (! $signed || ! str_contains($signed, '.')) {
            return null;
        }

        [$signature, $serialized] = explode('.', $signed, 2);

        return hash_equals(hash_hmac('sha256', $serialized, (string) config('app.key')), $signature)
            ? $serialized
            : null;
    }
}
