<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\SystemSetting;
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

    /**
     * Mode pembayaran aktif global: 'manual' atau 'midtrans'.
     */
    public static function activeMode(): string
    {
        $mode = (string) SystemSetting::get('payment_method_mode', 'manual');

        return in_array($mode, ['manual', 'midtrans'], true) ? $mode : 'manual';
    }

    /**
     * Server Key Midtrans: prioritas pengaturan Superadmin (system_settings), fallback ke .env.
     */
    public static function midtransServerKey(): string
    {
        $fromSettings = (string) SystemSetting::get('midtrans_server_key', '');

        return $fromSettings !== '' ? $fromSettings : (string) config('payment.midtrans.server_key');
    }

    public static function midtransClientKey(): string
    {
        $fromSettings = (string) SystemSetting::get('midtrans_client_key', '');

        return $fromSettings !== '' ? $fromSettings : (string) config('payment.midtrans.client_key');
    }

    public static function midtransIsProduction(): bool
    {
        $fromSettings = SystemSetting::get('midtrans_is_production');

        return $fromSettings === null
            ? (bool) config('payment.midtrans.is_production', false)
            : (bool) $fromSettings;
    }

    public static function midtransSnapUrl(): string
    {
        return self::midtransIsProduction()
            ? (string) config('payment.midtrans.snap_url_production')
            : (string) config('payment.midtrans.snap_url_sandbox');
    }

    public static function midtransSnapJsUrl(): string
    {
        return self::midtransIsProduction()
            ? (string) config('payment.midtrans.snap_js_url_production', 'https://app.midtrans.com/snap/snap.js')
            : (string) config('payment.midtrans.snap_js_url_sandbox', 'https://app.sandbox.midtrans.com/snap/snap.js');
    }

    public const MIDTRANS_AVAILABLE_CHANNELS = [
        'bca_va' => 'BCA Virtual Account',
        'echannel' => 'Mandiri Bill (VA)',
        'bni_va' => 'BNI Virtual Account',
        'bri_va' => 'BRI Virtual Account',
        'permata_va' => 'Permata Virtual Account',
        'cimb_va' => 'CIMB Niaga Virtual Account',
        'other_va' => 'Bank Lainnya (ATM Bersama / Prima)',
        'qris' => 'QRIS (BCA Mobile, Livin, GoPay, OVO, Dana)',
        'gopay' => 'GoPay E-Wallet',
        'shopeepay' => 'ShopeePay E-Wallet',
        'credit_card' => 'Kartu Kredit / Debit Online (Visa/Mastercard/JCB)',
    ];

    public function initiateGatewayPayment(
        Payment $payment,
        string $provider = 'midtrans',
        ?string $channel = null,
        bool $forceRegenerate = false
    ): Payment {
        if (self::activeMode() !== 'midtrans') {
            throw ValidationException::withMessages([
                'payment' => 'Metode pembayaran payment gateway sedang tidak diaktifkan oleh panitia.',
            ]);
        }

        $result = DB::transaction(function () use ($payment, $provider, $channel, $forceRegenerate) {
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

            // Hitung Biaya MDR / Fee Layanan Midtrans Berdasarkan Pengaturan Global
            $feeBorneBy = SystemSetting::get('fee_borne_by', 'team');
            $vaFee = (float) SystemSetting::get('midtrans_va_fee', 4000);
            $qrisFeePercent = (float) SystemSetting::get('midtrans_qris_fee_percent', 0.7);
            $ewalletFeePercent = (float) SystemSetting::get('midtrans_ewallet_fee_percent', 1.5);
            $ccFeePercent = (float) SystemSetting::get('midtrans_cc_fee_percent', 2.9);
            $ccFeeFixed = (float) SystemSetting::get('midtrans_cc_fee_fixed', 2000);

            $calculatedFee = $vaFee; // Standard VA flat fee sebagai baseline default pendaftaran klub
            if ($channel) {
                $channelLower = strtolower($channel);
                if (str_contains($channelLower, 'qris') || str_contains($channelLower, 'qr')) {
                    $calculatedFee = round((float) $lockedPayment->amount * ($qrisFeePercent / 100));
                } elseif (str_contains($channelLower, 'gopay') || str_contains($channelLower, 'shopeepay') || str_contains($channelLower, 'wallet') || str_contains($channelLower, 'ewallet')) {
                    $calculatedFee = round((float) $lockedPayment->amount * ($ewalletFeePercent / 100));
                } elseif (str_contains($channelLower, 'credit') || str_contains($channelLower, 'card') || str_contains($channelLower, 'kartu')) {
                    $calculatedFee = round(((float) $lockedPayment->amount * ($ccFeePercent / 100)) + $ccFeeFixed);
                }
            }

            $gatewayFee = $feeBorneBy === 'team' ? $calculatedFee : 0;
            $totalAmount = (float) $lockedPayment->amount + (float) $gatewayFee;

            $lockedPayment->gateway_fee = $gatewayFee;
            $lockedPayment->total_amount = $totalAmount;

            $snapToken = $lockedPayment->snap_token;
            $paymentUrl = $lockedPayment->payment_url;
            $externalRef = $lockedPayment->external_reference_id;

            if (config('payment.sandbox_simulator')) {
                $externalRef = strtoupper($provider).'-SIM-'.Str::upper(Str::random(10));
                if ($provider === 'midtrans') {
                    $snapToken = 'SNAP-SIM-'.Str::uuid()->toString();
                    $paymentUrl = null;
                } else {
                    $snapToken = null;
                    $paymentUrl = route('manager.payments', ['invoice' => $lockedPayment->invoice_number]);
                }
            } elseif ($provider === 'midtrans') {
                $channelMap = [
                    'BCA Virtual Account' => ['bca_va'],
                    'Mandiri Bill' => ['echannel'],
                    'BNI Virtual Account' => ['bni_va'],
                    'BRI Virtual Account' => ['bri_va'],
                    'Permata Virtual Account' => ['permata_va'],
                    'QRIS' => ['gopay', 'qris', 'shopeepay'],
                    'GoPay' => ['gopay'],
                    'ShopeePay' => ['shopeepay'],
                    'Credit Card' => ['credit_card'],
                ];

                $baseOrderId = $lockedPayment->invoice_number;

                // Midtrans menerapkan aturan unik pada order_id di mana satu order_id tidak boleh
                // didaftarkan ulang ke Snap API. Jika dipaksa regenerasi, atau token lama sudah ada,
                // kita tambahkan suffix unik attempt.
                $isReinitiation = $forceRegenerate
                    || ! empty($lockedPayment->snap_token)
                    || ! empty($lockedPayment->external_reference_id)
                    || ($channel !== null && $lockedPayment->payment_channel !== null && $lockedPayment->payment_channel !== $channel);

                $midtransOrderId = $isReinitiation
                    ? $baseOrderId . '-' . substr(time(), -5) . Str::upper(Str::random(3))
                    : $baseOrderId;

                $snapPayload = [
                    'transaction_details' => [
                        'order_id' => $midtransOrderId,
                        'gross_amount' => (int) round($totalAmount),
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
                    'callbacks' => [
                        'finish' => route('manager.payments', ['feedback' => 'finish']),
                        'unfinish' => route('manager.payments', ['feedback' => 'unfinish']),
                        'error' => route('manager.payments', ['feedback' => 'error']),
                    ],
                ];

                $channelsMode = SystemSetting::get('midtrans_channels_mode', 'all');
                $customChannels = (array) SystemSetting::get('midtrans_active_channels', []);

                if ($channel && isset($channelMap[$channel])) {
                    $snapPayload['enabled_payments'] = $channelMap[$channel];
                } elseif ($channelsMode === 'custom' && ! empty($customChannels)) {
                    $snapPayload['enabled_payments'] = array_values($customChannels);
                }
                // Jika mode 'all', jangan kirim enabled_payments sehingga seluruh metode yang diizinkan Midtrans otomatis tampil di Snap.

                $response = Http::withBasicAuth(self::midtransServerKey(), '')
                    ->acceptJson()
                    ->post(self::midtransSnapUrl(), $snapPayload);

                // Auto-recovery 1: Jika order_id ditolak karena sudah pernah digunakan di Midtrans (misal pada seeded data sandbox),
                // otomatis buatkan order_id ber-suffix unik dan coba kembali tanpa menampilkan error ke user.
                if (! $response->successful()) {
                    $respBody = (string) $response->body();
                    if (str_contains($respBody, 'order_id') && (str_contains($respBody, 'already been taken') || str_contains($respBody, 'sudah digunakan') || str_contains($respBody, 'has already'))) {
                        $midtransOrderId = $baseOrderId . '-' . substr(time(), -5) . Str::upper(Str::random(3));
                        $snapPayload['transaction_details']['order_id'] = $midtransOrderId;
                        $response = Http::withBasicAuth(self::midtransServerKey(), '')
                            ->acceptJson()
                            ->post(self::midtransSnapUrl(), $snapPayload);
                    }
                }

                // Auto-recovery 2: Jika filter enabled_payments gagal di akun Midtrans (misal bank tertentu belum diaktifkan), coba kembali tanpa filter
                if (! $response->successful() && isset($snapPayload['enabled_payments'])) {
                    unset($snapPayload['enabled_payments']);
                    $response = Http::withBasicAuth(self::midtransServerKey(), '')
                        ->acceptJson()
                        ->post(self::midtransSnapUrl(), $snapPayload);
                }

                if (! $response->successful()) {
                    \Illuminate\Support\Facades\Log::error('Midtrans Snap initiation failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'invoice' => $lockedPayment->invoice_number,
                        'order_id' => $midtransOrderId,
                    ]);

                    $errorMsg = $response->json('error_messages.0');

                    return [
                        'ok' => false,
                        'message' => 'Gagal menghubungi penyedia pembayaran Midtrans' . ($errorMsg ? ': ' . $errorMsg : '. Silakan coba kembali atau gunakan transfer manual.'),
                    ];
                }

                $body = $response->json();
                $snapToken = $body['token'] ?? null;
                $paymentUrl = $body['redirect_url'] ?? null;
                $externalRef = $midtransOrderId;
            } elseif ($provider === 'xendit') {
                $response = Http::withBasicAuth(config('payment.xendit.secret_key'), '')
                    ->acceptJson()
                    ->post(config('payment.xendit.invoice_url'), [
                        'external_id' => $lockedPayment->invoice_number,
                        'amount' => (int) round((float) $lockedPayment->total_amount),
                        'payer_email' => $team->manager_email,
                        'description' => 'Pendaftaran Tim '.$team->name.' - '.$lockedEvent->name,
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

            $savedChannel = $channel ?: 'Midtrans Snap (Multi-Channel)';

            $lockedPayment->update([
                'method' => $provider,
                'payment_channel' => $savedChannel,
                'gateway_fee' => $gatewayFee,
                'total_amount' => $totalAmount,
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
                'id' => $payment->external_reference_id ?: ('XND-'.Str::random(8)),
                'status' => 'PAID',
                'paid_amount' => (int) round((float) $payment->total_amount),
                'payment_channel' => $payment->payment_channel ?: 'QRIS',
            ], config('payment.xendit.callback_token'));
        }

        $orderId = $payment->external_reference_id ?: $payment->invoice_number;
        $statusCode = '200';
        $grossAmount = number_format((float) $payment->total_amount, 2, '.', '');
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.self::midtransServerKey());

        return $this->handleMidtransWebhook([
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => $payment->payment_channel ?: 'bca_va',
            'transaction_id' => $payment->external_reference_id ?: ('MID-'.Str::random(10)),
            'signature_key' => $signature,
        ]);
    }

    public function syncMidtransTransactionStatus(Payment $payment): Payment
    {
        if (self::activeMode() !== 'midtrans' || ! self::midtransServerKey() || in_array($payment->status, ['paid', 'refund_required'], true)) {
            return $payment;
        }

        $orderId = $payment->external_reference_id ?: $payment->invoice_number;
        $baseUrl = self::midtransIsProduction()
            ? 'https://api.midtrans.com/v2/'
            : 'https://api.sandbox.midtrans.com/v2/';

        try {
            $response = Http::withBasicAuth(self::midtransServerKey(), '')
                ->acceptJson()
                ->get($baseUrl.$orderId.'/status');

            if ($response->successful()) {
                $payload = $response->json();
                $txStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
                $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? 'accept'));

                $isSettled = $txStatus === 'settlement'
                    || ($txStatus === 'capture' && $fraudStatus === 'accept');
                $isExpired = in_array($txStatus, ['expire', 'cancel', 'deny'], true);

                if ($isSettled || $isExpired) {
                    return $this->processVerifiedGatewayCallback(
                        invoiceNumber: $orderId,
                        provider: 'midtrans',
                        channel: $payload['payment_type'] ?? null,
                        externalRef: $payload['transaction_id'] ?? null,
                        callbackAmount: (string) ($payload['gross_amount'] ?? $payment->total_amount),
                        isSettled: $isSettled,
                        isExpiredSignal: $isExpired,
                        rawPayload: $payload
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Sync Midtrans status failed: '.$e->getMessage());
        }

        return $payment;
    }

    public function handleMidtransWebhook(array $payload): Payment
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');

        $expectedSignature = hash(
            'sha512',
            $orderId.$statusCode.$grossAmount.self::midtransServerKey()
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
            callbackAmount: $grossAmount,
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
            callbackAmount: isset($payload['amount']) ? (string) $payload['amount'] : null,
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
        ?string $callbackAmount,
        bool $isSettled,
        bool $isExpiredSignal,
        array $rawPayload
    ): Payment {
        return DB::transaction(function () use (
            $invoiceNumber,
            $provider,
            $channel,
            $externalRef,
            $callbackAmount,
            $isSettled,
            $isExpiredSignal,
            $rawPayload
        ) {
            $payment = Payment::query()
                ->where('external_reference_id', $invoiceNumber)
                ->orWhere('invoice_number', $invoiceNumber)
                ->first();

            if (! $payment && preg_match('/^(INV-\d{4}-EVT\d+-\d+)/', $invoiceNumber, $matches)) {
                $payment = Payment::query()->where('invoice_number', $matches[1])->first();
            }

            if (! $payment) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Payment::class, [$invoiceNumber]);
            }

            $lockedEvent = Event::query()->whereKey($payment->event_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedTeam = $lockedPayment->team()->lockForUpdate()->firstOrFail();

            if ($callbackAmount !== null) {
                $cbFloat = (float) $callbackAmount;
                $validAmount = abs($cbFloat - (float) $lockedPayment->total_amount) <= 1.0
                    || abs($cbFloat - (float) $lockedPayment->amount) <= 1.0
                    || $cbFloat >= (float) $lockedPayment->amount;

                if (! is_numeric($callbackAmount) || ! $validAmount) {
                    throw new AccessDeniedHttpException('Nominal callback pembayaran tidak sesuai dengan invoice.');
                }
            }

            // Idempotency: jika sudah paid atau refund_required, abaikan sinyal duplikat
            if (in_array($lockedPayment->status, ['paid', 'refund_required'], true)) {
                return $lockedPayment;
            }

            if ($isSettled) {
                $previousStatus = $lockedPayment->status;
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

                        AuditLog::record(
                            entity: $lockedPayment,
                            action: 'payment.gateway.settled',
                            fromStatus: $previousStatus,
                            toStatus: 'refund_required',
                            reason: 'Pembayaran diterima setelah slot tidak tersedia.',
                            metadata: ['provider' => $provider, 'external_reference_id' => $externalRef],
                        );

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

                    AuditLog::record(
                        entity: $lockedPayment,
                        action: 'payment.gateway.settled',
                        fromStatus: $previousStatus,
                        toStatus: 'paid',
                        metadata: ['provider' => $provider, 'external_reference_id' => $externalRef],
                    );

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

                    AuditLog::record(
                        entity: $lockedPayment,
                        action: 'payment.gateway.settled',
                        fromStatus: $previousStatus,
                        toStatus: 'paid',
                        metadata: ['provider' => $provider, 'external_reference_id' => $externalRef],
                    );

                    if (! in_array($lockedTeam->status, ['locked_roster', 'disqualified'], true)) {
                        $lockedTeam->update([
                            'status' => 'active',
                            'slot_expires_at' => null,
                        ]);
                    }

                    $lockedEvent->syncQuotaStatus();
                }

                app(EmailNotificationService::class)->sendPaymentConfirmed($lockedTeam, $lockedPayment);

                return $lockedPayment->fresh();
            }

            if ($isExpiredSignal && in_array($lockedPayment->status, ['unpaid', 'rejected'], true)) {
                $previousStatus = $lockedPayment->status;
                $lockedPayment->update([
                    'status' => 'expired',
                    'webhook_payload' => $rawPayload,
                ]);

                AuditLog::record(
                    entity: $lockedPayment,
                    action: 'payment.gateway.expired',
                    fromStatus: $previousStatus,
                    toStatus: 'expired',
                    metadata: ['provider' => $provider],
                );

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
        if (self::activeMode() !== 'manual') {
            throw ValidationException::withMessages([
                'payment' => 'Metode transfer bank manual sedang tidak diaktifkan oleh panitia.',
            ]);
        }

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

            AuditLog::record(
                entity: $lockedPayment,
                action: 'payment.manual.approved',
                actor: $verifier,
                fromStatus: 'waiting_verification',
                toStatus: 'paid',
            );

            app(EmailNotificationService::class)->sendPaymentConfirmed($lockedTeam, $lockedPayment);

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

            app(EmailNotificationService::class)->sendPaymentRejected($lockedTeam, $lockedPayment, $reason);

            AuditLog::record(
                entity: $lockedPayment,
                action: 'payment.manual.rejected',
                actor: $verifier,
                fromStatus: 'waiting_verification',
                toStatus: 'rejected',
                reason: trim($reason),
                metadata: ['expires_at' => $newExpiresAt->toIso8601String()],
            );

            return $lockedPayment->fresh();
        });
    }

    /**
     * Menandai transaksi berstatus refund_required menjadi refunded setelah Finance melakukan pengembalian dana.
     */
    public function markRefunded(Payment $payment, User $verifier, string $refundNotes): Payment
    {
        return DB::transaction(function () use ($payment, $verifier, $refundNotes) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedTeam = $lockedPayment->team()->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === 'refunded') {
                return $lockedPayment;
            }

            if ($lockedPayment->status !== 'refund_required') {
                throw ValidationException::withMessages([
                    'payment' => 'Hanya transaksi berstatus Perlu Refund (refund_required) yang dapat ditandai selesai refund.',
                ]);
            }

            $cleanNotes = trim($refundNotes);

            $lockedPayment->update([
                'status' => 'refunded',
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'rejection_reason' => 'REFUND SELESAI: '.$cleanNotes,
            ]);

            AuditLog::record(
                entity: $lockedPayment,
                action: 'payment.refund.completed',
                actor: $verifier,
                fromStatus: 'refund_required',
                toStatus: 'refunded',
                reason: $cleanNotes,
            );

            app(EmailNotificationService::class)->sendPaymentRefunded($lockedTeam, $lockedPayment, $cleanNotes);

            return $lockedPayment->fresh();
        });
    }
}
