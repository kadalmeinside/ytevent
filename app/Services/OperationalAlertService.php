<?php

namespace App\Services;

use App\Models\WebhookLog;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OperationalAlertService
{
    public function webhookFailed(WebhookLog $log): void
    {
        $this->send(
            'YTEvent alert: webhook payment gagal',
            [
                'Provider' => $log->provider,
                'Invoice' => $log->invoice_number ?: '-',
                'Reference' => $log->external_reference ?: '-',
                'HTTP status' => $log->http_status ?: '-',
                'Error' => $log->error_message ?: '-',
            ]
        );
    }

    public function jobFailed(object $job): void
    {
        $this->send(
            'YTEvent alert: queue job gagal',
            [
                'Queue' => method_exists($job->job, 'getQueue') ? $job->job->getQueue() : '-',
                'Connection' => $job->connectionName ?? '-',
                'UUID' => method_exists($job->job, 'getJobId') ? $job->job->getJobId() : '-',
                'Waktu' => now()->toDateTimeString(),
            ]
        );
    }

    private function send(string $subject, array $details): void
    {
        $recipient = config('observability.alerts.email');
        if (! config('observability.alerts.enabled') || ! is_string($recipient) || $recipient === '') {
            return;
        }

        try {
            Mail::raw(collect($details)->map(fn ($value, $key) => "{$key}: {$value}")->implode("\n"), function ($message) use ($recipient, $subject): void {
                $message->to($recipient)->subject($subject);
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
