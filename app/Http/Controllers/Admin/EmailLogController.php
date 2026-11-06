<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RetryEmailLogRequest;
use App\Models\EmailNotificationLog;
use App\Models\Event;
use App\Services\EmailNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function __construct(
        protected EmailNotificationService $emailService
    ) {}

    /**
     * Panel pemantauan antrean & riwayat pengiriman email transaksional.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => in_array($request->query('status'), ['queued', 'sent', 'failed'], true) ? $request->query('status') : null,
            'type' => array_key_exists((string) $request->query('type'), EmailNotificationLog::MAILABLE_LABELS) ? $request->query('type') : null,
            'event_id' => $request->integer('event_id') ?: null,
            'search' => trim((string) $request->query('search', '')) ?: null,
        ];

        $logs = EmailNotificationLog::query()
            ->with(['team:id,name', 'event:id,name'])
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'], fn ($q, $v) => $q->where('mailable_type', $v))
            ->when($filters['event_id'], fn ($q, $v) => $q->where('event_id', $v))
            ->when($filters['search'], function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->where('recipient_email', 'like', "%{$v}%")
                        ->orWhere('subject', 'like', "%{$v}%")
                        ->orWhereHas('team', fn ($t) => $t->where('name', 'like', "%{$v}%"));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (EmailNotificationLog $log) => [
                'id' => $log->id,
                'mailable_type' => $log->mailable_type,
                'type_label' => EmailNotificationLog::MAILABLE_LABELS[$log->mailable_type] ?? $log->mailable_type,
                'recipient_email' => $log->recipient_email,
                'subject' => $log->subject,
                'status' => $log->status,
                'attempts' => $log->attempts,
                'error_message' => $log->error_message,
                'is_retryable' => $log->isRetryable(),
                'team_name' => $log->team?->name,
                'event_name' => $log->event?->name,
                'created_at' => $log->created_at?->format('d/m/Y H:i'),
                'sent_at' => $log->sent_at?->format('d/m/Y H:i'),
                'last_attempted_at' => $log->last_attempted_at?->format('d/m/Y H:i'),
            ]);

        $counts = EmailNotificationLog::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Admin/EmailLogs/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'metrics' => [
                'total' => (int) $counts->sum(),
                'queued' => (int) ($counts['queued'] ?? 0),
                'sent' => (int) ($counts['sent'] ?? 0),
                'failed' => (int) ($counts['failed'] ?? 0),
            ],
            'events' => Event::query()->orderByDesc('id')->get(['id', 'name']),
            'mailableTypes' => collect(EmailNotificationLog::MAILABLE_LABELS)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                ->values(),
        ]);
    }

    /**
     * Kirim ulang satu email yang gagal.
     */
    public function retry(RetryEmailLogRequest $request, EmailNotificationLog $log): RedirectResponse
    {
        if (! $log->isRetryable()) {
            return back()->with('error', 'Email ini tidak dapat dikirim ulang karena statusnya bukan gagal atau data email tidak tersedia.');
        }

        $updated = $this->emailService->retry($log);

        return back()->with(
            $updated->status === EmailNotificationLog::STATUS_FAILED ? 'error' : 'success',
            match ($updated->status) {
                EmailNotificationLog::STATUS_SENT => "Email ke {$log->recipient_email} berhasil dikirim ulang.",
                EmailNotificationLog::STATUS_QUEUED => "Email ke {$log->recipient_email} dimasukkan kembali ke antrean pengiriman.",
                default => "Pengiriman ulang ke {$log->recipient_email} masih gagal: {$updated->error_message}",
            }
        );
    }

    /**
     * Kirim ulang seluruh email gagal (opsional per kompetisi).
     */
    public function retryAllFailed(RetryEmailLogRequest $request): RedirectResponse
    {
        $logs = EmailNotificationLog::query()
            ->where('status', EmailNotificationLog::STATUS_FAILED)
            ->whereNotNull('payload')
            ->when($request->validated('event_id'), fn ($q, $v) => $q->where('event_id', $v))
            ->orderBy('id')
            ->limit(200)
            ->get();

        if ($logs->isEmpty()) {
            return back()->with('error', 'Tidak ada email gagal yang dapat dikirim ulang.');
        }

        foreach ($logs as $log) {
            $this->emailService->retry($log);
        }

        return back()->with('success', "{$logs->count()} email gagal telah diproses ulang.");
    }
}

