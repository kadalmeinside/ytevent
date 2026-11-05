<?php

namespace App\Services;

use App\Models\EmailNotificationLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PaymentService
{
    public function __construct(
        protected QuotaExpirationService $quotaExpiration
    ) {}

    public function initiateGatewayPayment(Payment $payment, string $provider, string $channel): Payment
    {
        $result = DB::transaction(function () use ($payment, $provider, $channel) {
            $lockedEvent = Event::query()->whereKey($payment->event_id)->lockForUpdate()->firstOrFail();
            $this->quotaExpiration->sweepExpiredForEvent($lockedEvent);

            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedPayment->status, ['unpaid', 'rejected'], true) || $lockedPayment->isPastExpiry()) {
                return [
                    'ok' => false,
                    'message' => 'Batas waktu pembayaran untuk tagihan ini telah berakhir. Silakan ajukan ulang slot pendaftaran jika kuota masih tersedia.',
                ];
            }

            $team = $lockedPayment->team;
            $expiryMinutes = $lockedPayment->expires_at
                ? max(5, (int) now()->diffInMinutes($lockedPayment->expires_at, false))
                : 1440;

            $snapToken = $lockedPayment->snap_token;
            $paymentUrl = $lockedPayment->payment_url;
            $externalRef = $lockedPayment->external_reference_id;

            if (config('payment.sandbox_simulator')) {
                $externalRef = strtoupper($provider) . '-SIM-' . Str::upper(Str::random(10));
                if ($provider === 'midtrans') {
                    $snapToken = 'SNAP-SIM-' . Str::uuid()->toString();
                    $paymentUrl = null;
                } else {
                    $snapToken = null;
                    $paymentUrl = route('manager.payments', ['invoice' => $lockedPayment->invoice_number]);
                }
            } elseif ($provider === 'midtrans') {
                $response = Http::withBasicAuth(config('payment.midtrans.server_key'), '')
                    ->acceptJson()
                    ->post(config('payment.midtrans.snap_url'), [
                        'transaction_details' => [
                            'order_id' => $lockedPayment->invoice_number,
                            'gross_amount' => (int) round((float) $lockedPayment->total_amount),
                        ],
                        'customer_details' => [
                            'first_name' => $team->manager_name,
                            'email' => $team->manager_email,
                            'phone' => $team->manager_whatsapp,
                        ],
                        'expiry' => [
                            'unit' => 'minute',
                            'duration' => $expiryMinutes,
                        ],
                    ]);

                if (! $response->successful()) {
                    return [
                        'ok' => false,
                        'message' => 'Gagal menghubungi penyedia pembayaran Midtrans. Silakan coba kembali atau gunakan transfer manual.',
                    ];
                }

                $body = $response->json();
                $snapToken = $body['token'] ?? null;
                $paymentUrl = $body['redirect_url'] ?? null;
                $externalRef = $lockedPayment->invoice_number;
            } elseif ($provider === 'xendit') {
                $response = Http::withBasicAuth(config('payment.xendit.secret_key'), '')
                    ->acceptJson()
                    ->post(config('payment.xendit.invoice_url'), [
                        'external_id' => $lockedPayment->invoice_number,
                        'amount' => (int) round((float) $lockedPayment->total_amount),
                        'payer_email' => $team->manager_email,
                        'description' => 'Pendaftaran Tim ' . $team->name . ' - ' . $lockedEvent->name,
                        'invoice_duration' => $expiryMinutes * 60,
                    ]);

                if (! $response->successful()) {
                    return [
                        'ok' => false,
                        'message' => 'Gagal menghubungi penyedia pembayaran Xendit. Silakan coba kembali atau gunakan transfer manual.',
                    ];
                }

                $body = $response->json();
                $externalRef = $body['id'] ?? $lockedPayment->invoice_number;
                $paymentUrl = $body['invoice_url'] ?? null;
                $snapToken = null;
            }

            $lockedPayment->update([
                'method' => $provider,
                'payment_channel' => $channel,
                'external_reference_id' => $externalRef,
                'snap_token' => $snapToken,
                'payment_url' => $paymentUrl,
            ]);

            return [
                'ok' => true,
                'payment' => $lockedPayment->fresh(),
            ];
        });

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'payment' => $result['message'],
            ]);
        }

        return $result['payment'];
    }

    /**
     * Simulator lokal (hanya aktif jika PAYMENT_SANDBOX_SIMULATOR=true) yang memicu alur webhook bertanda tangan asli.
     */
    public function simulateSandboxSettlement(Payment $payment): Payment
    {
        if (! config('payment.sandbox_simulator')) {
            throw new AccessDeniedHttpException('Simulator pembayaran hanya aktif pada mode pengembangan.');
        }

        $provider = $payment->method ?: 'midtrans';

        if ($provider === 'xendit') {
            return $this->handleXenditWebhook([
                'external_id' => $payment->invoice_number,
                'id' => $payment->external_reference_id ?: ('XND-' . Str::random(8)),
                'status' => 'PAID',
                'paid_amount' => (int) round((float) $payment->total_amount),
                'payment_channel' => $payment->payment_channel ?: 'QRIS',
            ], config('payment.xendit.callback_token'));
        }

        $orderId = $payment->invoice_number;
        $statusCode = '200';
        $grossAmount = number_format((float) $payment->total_amount, 2, '.', '');
        $signature = hash('sha512', $orderId . $statusCode . $grossAmount . config('payment.midtrans.server_key'));

        return $this->handleMidtransWebhook([
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => $payment->payment_channel ?: 'bca_va',
            'transaction_id' => $payment->external_reference_id ?: ('MID-' . Str::random(10)),
            'signature_key' => $signature,
        ]);
    }

    public function handleMidtransWebhook(array $payload): Payment
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');

        $expectedSignature = hash(
            'sha512',
            $orderId . $statusCode . $grossAmount . config('payment.midtrans.server_key')
        );

        if ($orderId === '' || $signatureKey === '' || ! hash_equals($expectedSignature, $signatureKey)) {
            throw new AccessDeniedHttpException('Signature Midtrans tidak valid.');
        }

        $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? 'accept'));

        $isSettled = $transactionStatus === 'settlement'
            || ($transactionStatus === 'capture' && $fraudStatus === 'accept');

        $isExpired = in_array($transactionStatus, ['expire', 'cancel', 'deny'], true);

        return $this->processVerifiedGatewayCallback(
            invoiceNumber: $orderId,
            provider: 'midtrans',
            channel: $payload['payment_type'] ?? null,
            externalRef: $payload['transaction_id'] ?? null,
            isSettled: $isSettled,
            isExpiredSignal: $isExpired,
            rawPayload: $payload
        );
    }

    public function handleXenditWebhook(array $payload, ?string $callbackToken): Payment
    {
        $expectedToken = (string) config('payment.xendit.callback_token');

        if ($expectedToken === '' || ! hash_equals($expectedToken, (string) $callbackToken)) {
            throw new AccessDeniedHttpException('Callback token Xendit tidak valid.');
        }

        $invoiceNumber = (string) ($payload['external_id'] ?? '');
        if ($invoiceNumber === '') {
            throw new AccessDeniedHttpException('Nomor referensi invoice Xendit tidak ditemukan.');
        }

        $status = strtoupper((string) ($payload['status'] ?? ''));
        $isSettled = in_array($status, ['PAID', 'SETTLED'], true);
        $isExpired = $status === 'EXPIRED';

        return $this->processVerifiedGatewayCallback(
            invoiceNumber: $invoiceNumber,
            provider: 'xendit',
            channel: $payload['payment_channel'] ?? null,
            externalRef: $payload['id'] ?? null,
            isSettled: $isSettled,
            isExpiredSignal: $isExpired,
            rawPayload: $payload
        );
    }

    protected function processVerifiedGatewayCallback(
        string $invoiceNumber,
        string $provider,
        ?string $channel,
        ?string $externalRef,
        bool $isSettled,
        bool $isExpiredSignal,
        array $rawPayload
    ): Payment {
        return DB::transaction(function () use (
            $invoiceNumber,
            $provider,
            $channel,
            $externalRef,
            $isSettled,
            $isExpiredSignal,
            $rawPayload
        ) {
            $payment = Payment::query()->where('invoice_number', $invoiceNumber)->firstOrFail();
            $lockedEvent = Event::query()->whereKey($payment->event_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedTeam = $lockedPayment->team()->lockForUpdate()->firstOrFail();

            // Idempotency: jika sudah paid atau refund_required, abaikan sinyal duplikat
            if (in_array($lockedPayment->status, ['paid', 'refund_required'], true)) {
                return $lockedPayment;
            }

            if ($isSettled) {
                $wasExpired = $lockedPayment->status === 'expired' || $lockedTeam->status === 'expired';

                if ($wasExpired) {
                    $this->quotaExpiration->sweepExpiredForEvent($lockedEvent);
                    $occupied = $lockedEvent->teams()->occupyingQuotaAt(now())->count();

                    // Over-Quota Guard: dilarang melampaui max_teams meskipun ada late webhook settlement
                    if ($occupied >= $lockedEvent->max_teams) {
                        $lockedPayment->update([
                            'method' => $provider,
                            'payment_channel' => $channel ?: $lockedPayment->payment_channel,
                            'external_reference_id' => $externalRef ?: $lockedPayment->external_reference_id,
                            'status' => 'refund_required',
                            'paid_at' => now(),
                            'webhook_payload' => $rawPayload,
                        ]);

                        return $lockedPayment->fresh();
                    }

                    // Slot masih tersedia -> pulihkan slot secara aman
                    $lockedPayment->update([
                        'method' => $provider,
                        'payment_channel' => $channel ?: $lockedPayment->payment_channel,
                        'external_reference_id' => $externalRef ?: $lockedPayment->external_reference_id,
                        'status' => 'paid',
                        'paid_at' => now(),
                        'webhook_payload' => $rawPayload,
                    ]);

                    $lockedTeam->update([
                        'status' => 'active',
                        'slot_expires_at' => null,
                    ]);

                    $lockedEvent->syncQuotaStatus($occupied + 1);
                } else {
                    $lockedPayment->update([
                        'method' => $provider,
                        'payment_channel' => $channel ?: $lockedPayment->payment_channel,
                        'external_reference_id' => $externalRef ?: $lockedPayment->external_reference_id,
                        'status' => 'paid',
                        'paid_at' => now(),
                        'webhook_payload' => $rawPayload,
                    ]);

                    if (! in_array($lockedTeam->status, ['locked_roster', 'disqualified'], true)) {
                        $lockedTeam->update([
                            'status' => 'active',
                            'slot_expires_at' => null,
                        ]);
                    }

                    $lockedEvent->syncQuotaStatus();
                }

                EmailNotificationLog::create([
                    'event_id' => $lockedEvent->id,
                    'team_id' => $lockedTeam->id,
                    'mailable_type' => 'PaymentReceivedMail',
                    'recipient_email' => $lockedTeam->manager_email,
                    'status' => 'queued',
                ]);

                return $lockedPayment->fresh();
            }

            if ($isExpiredSignal && in_array($lockedPayment->status, ['unpaid', 'rejected'], true)) {
                $lockedPayment->update([
                    'status' => 'expired',
                    'webhook_payload' => $rawPayload,
                ]);

                if ($lockedTeam->status === 'pending_payment') {
                    $lockedTeam->update(['status' => 'expired']);
                }

                $lockedEvent->syncQuotaStatus();
            }

            return $lockedPayment->fresh();
        });
    }

    public function uploadManualProof(Payment $payment, array $validated, UploadedFile $proofFile): Payment
    {
        $storedPath = $proofFile->store('payments/proofs', 'private');

        try {
            $result = DB::transaction(function () use ($payment, $validated, $storedPath) {
                $lockedEvent = Event::query()->whereKey($payment->event_id)->lockForUpdate()->firstOrFail();
                $this->quotaExpiration->sweepExpiredForEvent($lockedEvent);

                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                if (! in_array($lockedPayment->status, ['unpaid', 'rejected'], true) || $lockedPayment->isPastExpiry()) {
                    return [
                        'ok' => false,
                        'message' => 'Batas waktu pembayaran untuk tagihan ini telah berakhir. Silakan ajukan ulang slot pendaftaran jika kuota masih tersedia.',
                    ];
                }

                $oldProof = $lockedPayment->proof_file_path;

                $lockedPayment->update([
                    'method' => 'manual',
                    'payment_channel' => 'Transfer Bank Manual',
                    'sender_bank_name' => trim($validated['sender_bank_name']),
                    'sender_account_holder' => trim($validated['sender_account_holder']),
                    'sender_account_number' => isset($validated['sender_account_number']) ? trim($validated['sender_account_number']) : null,
                    'transfer_date' => $validated['transfer_date'],
                    'proof_file_path' => $storedPath,
                    'status' => 'waiting_verification',
                    'rejection_reason' => null,
                ]);

                return [
                    'ok' => true,
                    'payment' => $lockedPayment->fresh(),
                    'old_proof' => $oldProof,
                ];
            });
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($storedPath);
            throw $e;
        }

        if (! $result['ok']) {
            Storage::disk('private')->delete($storedPath);
            throw ValidationException::withMessages([
                'proof_file' => $result['message'],
            ]);
        }

        if (! empty($result['old_proof']) && $result['old_proof'] !== $storedPath) {
            Storage::disk('private')->delete($result['old_proof']);
        }

        return $result['payment'];
    }

    public function approveManualPayment(Payment $payment, User $verifier): Payment
    {
        return DB::transaction(function () use ($payment, $verifier) {
            $lockedEvent = Event::query()->whereKey($payment->event_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedTeam = $lockedPayment->team()->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === 'paid') {
                return $lockedPayment;
            }

            if ($lockedPayment->status !== 'waiting_verification') {
                throw ValidationException::withMessages([
                    'payment' => 'Hanya pembayaran berstatus Menunggu Verifikasi yang dapat disetujui.',
                ]);
            }

            $now = now();

            $lockedPayment->update([
                'status' => 'paid',
                'verified_by' => $verifier->id,
                'verified_at' => $now,
                'paid_at' => $now,
                'rejection_reason' => null,
            ]);

            if (! in_array($lockedTeam->status, ['locked_roster', 'disqualified'], true)) {
                $lockedTeam->update([
                    'status' => 'active',
                    'slot_expires_at' => null,
                ]);
            }

            $lockedEvent->syncQuotaStatus();

            EmailNotificationLog::create([
                'event_id' => $lockedEvent->id,
                'team_id' => $lockedTeam->id,
                'mailable_type' => 'PaymentReceivedMail',
                'recipient_email' => $lockedTeam->manager_email,
                'status' => 'queued',
            ]);

            return $lockedPayment->fresh();
        });
    }

    public function rejectManualPayment(Payment $payment, User $verifier, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $verifier, $reason) {
            $lockedEvent = Event::query()->whereKey($payment->event_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedTeam = $lockedPayment->team()->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'waiting_verification') {
                throw ValidationException::withMessages([
                    'payment' => 'Hanya pembayaran berstatus Menunggu Verifikasi yang dapat ditolak.',
                ]);
            }

            $now = now();
            $graceHours = max(1, (int) ($lockedEvent->reupload_grace_hours ?: 6));
            $graceDeadline = $now->copy()->addHours($graceHours);
            $newExpiresAt = ($lockedPayment->expires_at && $lockedPayment->expires_at->gt($graceDeadline))
                ? $lockedPayment->expires_at
                : $graceDeadline;

            $lockedPayment->update([
                'status' => 'rejected',
                'verified_by' => $verifier->id,
                'verified_at' => $now,
                'rejection_reason' => trim($reason),
                'expires_at' => $newExpiresAt,
            ]);

            if ($lockedTeam->status === 'pending_payment') {
                $lockedTeam->update([
                    'slot_expires_at' => $newExpiresAt,
                ]);
            }

            EmailNotificationLog::create([
                'event_id' => $lockedEvent->id,
                'team_id' => $lockedTeam->id,
                'mailable_type' => 'PaymentRejectedMail',
                'recipient_email' => $lockedTeam->manager_email,
                'status' => 'queued',
            ]);

            return $lockedPayment->fresh();
        });
    }
}

