<?php

namespace App\Jobs;

use App\Models\EmailNotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mengirim satu email transaksional yang tercatat di email_notification_logs.
 *
 * Kegagalan pengiriman tidak dilempar ulang (fail-safe) agar alur bisnis utama
 * (pendaftaran, verifikasi, penguncian skuad) tidak terganggu. Pemulihan dilakukan
 * melalui tombol "Kirim Ulang" di panel admin.
 */
class SendLoggedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $logId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $log = EmailNotificationLog::find($this->logId);

        if (! $log || $log->status === EmailNotificationLog::STATUS_SENT) {
            return;
        }

        $log->forceFill([
            'attempts' => $log->attempts + 1,
            'last_attempted_at' => now(),
        ])->save();

        try {
            $serialized = \App\Services\EmailNotificationService::verifyPayload($log->payload);
            $mailable = $serialized !== null ? unserialize($serialized) : null;

            if (! $mailable instanceof Mailable) {
                throw new \RuntimeException('Payload email tidak valid atau tidak dapat direkonstruksi.');
            }

            Mail::to($log->recipient_email)->send($mailable);

            $log->update([
                'status' => EmailNotificationLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            report($e);

            $log->update([
                'status' => EmailNotificationLog::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);
        }
    }
}

