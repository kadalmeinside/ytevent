<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventSession;
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
     * Mode pembayaran aktif global: 'manual', 'midtrans', 'xendit', atau 'gateway'.
     */
    public static function activeMode(): string
    {
        $mode = (string) SystemSetting::get('payment_method_mode', 'manual');

        return in_array($mode, ['manual', 'midtrans', 'xendit', 'gateway'], true) ? $mode : 'manual';
    }

    public static function isGatewayMode(): bool
    {
        return in_array(self::activeMode(), ['midtrans', 'xendit', 'gateway'], true);
    }

    public static function activeGatewayProvider(): string
    {
        if (self::activeMode() === 'xendit') {
            return 'xendit';
        }

        $configuredDefault = (string) SystemSetting::get('payment_gateway_default', '');

        return in_array($configuredDefault, ['midtrans', 'xendit'], true)
            ? $configuredDefault
            : 'midtrans';
    }

    public static function xenditSecretKey(): string
    {
        $fromSettings = (string) SystemSetting::get('xendit_secret_key', '');

        return $fromSettings !== '' ? $fromSettings : (string) config('payment.xendit.secret_key');
    }

    public static function xenditCallbackToken(): string
    {
        $fromSettings = (string) SystemSetting::get('xendit_callback_token', '');

        return $fromSettings !== '' ? $fromSettings : (string) config('payment.xendit.callback_token');
    }

    public static function isProviderEnabled(string $provider): bool
    {
        if (! self::isGatewayMode() || ! in_array($provider, ['midtrans', 'xendit'], true)) {
            return false;
        }

        if (self::activeMode() === 'xendit' && $provider !== 'xendit') {
            return false;
        }

        $defaultGateway = (string) SystemSetting::get('payment_gateway_default', '');
        $enabledProviders = array_values(array_filter((array) SystemSetting::get('payment_enabled_providers', [])));

        if (! empty($enabledProviders) && ! in_array($provider, $enabledProviders, true)) {
            return false;
        }

        if ($defaultGateway !== '' && empty($enabledProviders) && $defaultGateway !== $provider) {
            return false;
        }

        if (config('payment.sandbox_simulator')) {
            return true;
        }

        return match ($provider) {
            'midtrans' => self::midtransServerKey() !== '',
            'xendit' => self::xenditSecretKey() !== '',
        };
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

    public const MIDTRANS_CHANNEL_ALIASES = [
        'BCA Virtual Account' => 'bca_va',
        'Mandiri Bill' => 'echannel',
        'Mandiri Bill (VA)' => 'echannel',
        'BNI Virtual Account' => 'bni_va',
        'BRI Virtual Account' => 'bri_va',
        'Permata Virtual Account' => 'permata_va',
        'CIMB Niaga Virtual Account' => 'cimb_va',
        'Bank Lainnya (ATM Bersama / Prima)' => 'other_va',
        'QRIS' => 'qris',
        'GoPay' => 'gopay',
        'ShopeePay' => 'shopeepay',
        'Credit Card' => 'credit_card',
    ];

    public const XENDIT_AVAILABLE_CHANNELS = [
        'QRIS' => 'QRIS',
        'BCA' => 'BCA Virtual Account',
        'MANDIRI' => 'Mandiri Virtual Account',
        'BNI' => 'BNI Virtual Account',
        'BRI' => 'BRI Virtual Account',
        'PERMATA' => 'Permata Virtual Account',
        'OVO' => 'OVO E-Wallet',
        'DANA' => 'DANA E-Wallet',
        'SHOPEEPAY' => 'ShopeePay E-Wallet',
        'LINKAJA' => 'LinkAja E-Wallet',
        'CREDIT_CARD' => 'Kartu Kredit / Debit Online',
    ];

    public const XENDIT_CHANNEL_ALIASES = [
        'qris' => 'QRIS',
        'bca_va' => 'BCA',
        'BCA Virtual Account' => 'BCA',
        'echannel' => 'MANDIRI',
        'Mandiri Bill' => 'MANDIRI',
        'Mandiri Bill (VA)' => 'MANDIRI',
        'bni_va' => 'BNI',
        'BNI Virtual Account' => 'BNI',
        'bri_va' => 'BRI',
        'BRI Virtual Account' => 'BRI',
        'permata_va' => 'PERMATA',
        'Permata Virtual Account' => 'PERMATA',
        'shopeepay' => 'SHOPEEPAY',
        'ShopeePay' => 'SHOPEEPAY',
        'credit_card' => 'CREDIT_CARD',
        'Credit Card' => 'CREDIT_CARD',
    ];

    /**
     * Daftar lengkap nilai payment_channel resmi yang diizinkan untuk provider tertentu.
     */
    public static function allowedChannelsForProvider(string $provider): array
    {
        return match ($provider) {
            'midtrans' => array_values(array_unique(array_merge(
                array_keys(self::MIDTRANS_AVAILABLE_CHANNELS),
                array_keys(self::MIDTRANS_CHANNEL_ALIASES),
            ))),
            'xendit' => array_values(array_unique(array_merge(
                array_keys(self::XENDIT_AVAILABLE_CHANNELS),
                array_keys(self::XENDIT_CHANNEL_ALIASES),
            ))),
            default => [],
        };
    }

    /**
     * Normalisasi channel menjadi konfigurasi provider dan kategori biaya MDR.
     *
     * @return array{canonical: string, enabled_payments: array<int, string>, fee_category: string, saved_label: string}|null
     */
    public static function resolveProviderChannel(string $provider, ?string $channel): ?array
    {
        $trimmed = $channel !== null ? trim($channel) : '';
        if ($trimmed === '') {
            return null;
        }

        if ($provider === 'midtrans') {
            $canonical = self::MIDTRANS_CHANNEL_ALIASES[$trimmed] ?? $trimmed;
            if (! array_key_exists($canonical, self::MIDTRANS_AVAILABLE_CHANNELS)) {
                throw ValidationException::withMessages([
                    'payment_channel' => 'Saluran pembayaran Midtrans yang dipilih tidak valid.',
                ]);
            }

            $enabledPayments = match ($canonical) {
                'qris' => ['gopay', 'qris', 'shopeepay'],
                default => [$canonical],
            };

            $feeCategory = match ($canonical) {
                'qris' => 'qris',
                'gopay', 'shopeepay' => 'ewallet',
                'credit_card' => 'cc',
                default => 'va',
            };

            return [
                'canonical' => $canonical,
                'enabled_payments' => $enabledPayments,
                'fee_category' => $feeCategory,
                'saved_label' => $trimmed,
            ];
        }

        if ($provider === 'xendit') {
            $canonical = self::XENDIT_CHANNEL_ALIASES[$trimmed] ?? strtoupper($trimmed);
            if (! array_key_exists($canonical, self::XENDIT_AVAILABLE_CHANNELS)) {
                throw ValidationException::withMessages([
                    'payment_channel' => 'Saluran pembayaran Xendit yang dipilih tidak valid.',
                ]);
            }

            $feeCategory = match ($canonical) {
                'QRIS' => 'qris',
                'OVO', 'DANA', 'SHOPEEPAY', 'LINKAJA' => 'ewallet',
                'CREDIT_CARD' => 'cc',
                default => 'va',
            };

            return [
                'canonical' => $canonical,
                'enabled_payments' => [$canonical],
                'fee_category' => $feeCategory,
                'saved_label' => $trimmed,
            ];
        }

        return null;
    }

    protected function calculateGatewayFee(float $baseAmount, ?string $feeCategory): float
    {
        if (SystemSetting::get('fee_borne_by', 'team') !== 'team') {
            return 0.0;
        }

        $vaFee = (float) SystemSetting::get('midtrans_va_fee', 4000);
        $qrisFeePercent = (float) SystemSetting::get('midtrans_qris_fee_percent', 0.7);
        $ewalletFeePercent = (float) SystemSetting::get('midtrans_ewallet_fee_percent', 1.5);
        $ccFeePercent = (float) SystemSetting::get('midtrans_cc_fee_percent', 2.9);
        $ccFeeFixed = (float) SystemSetting::get('midtrans_cc_fee_fixed', 2000);

        return match ($feeCategory) {
            'qris' => round($baseAmount * ($qrisFeePercent / 100)),
            'ewallet' => round($baseAmount * ($ewalletFeePercent / 100)),
            'cc' => round(($baseAmount * ($ccFeePercent / 100)) + $ccFeeFixed),
            default => $vaFee,
        };
    }

    public function initiateGatewayPayment(
        Payment $payment,
        string $provider = 'midtrans',
        ?string $channel = null,
        bool $forceRegenerate = false
    ): Payment {
        if (! self::isGatewayMode()) {
            throw ValidationException::withMessages([
                'payment' => 'Metode pembayaran payment gateway sedang tidak diaktifkan oleh panitia.',
            ]);
        }

        if (! self::isProviderEnabled($provider)) {
            throw ValidationException::withMessages([
                'payment' => "Penyedia payment gateway {$provider} sedang tidak diaktifkan atau belum dikonfigurasi.",
            ]);
        }

        $resolvedChannel = self::resolveProviderChannel($provider, $channel);
        $claimToken = (string) Str::uuid();

        // Langkah 1 (Transaksi Singkat): Lock row, sweep kuota, validasi status, hitung biaya, dan pasang claim lock agar klik ganda tidak membuat invoice rangkap.
        $prepared = DB::transaction(function () use ($payment, $provider, $resolvedChannel, $forceRegenerate, $claimToken) {
            $lockedEvent = Event::query()->whereKey($payment->event_id)->lockForUpdate()->firstOrFail();
            $this->quotaExpiration->sweepExpiredForEvent($lockedEvent);

            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === 'paid') {
                return [
                    'status' => 'already_paid',
                    'payment' => $lockedPayment,
                ];
            }

            if (! in_array($lockedPayment->status, ['unpaid', 'rejected'], true) || $lockedPayment->isPastExpiry()) {
                return [
                    'status' => 'error',
                    'message' => 'Batas waktu pembayaran untuk tagihan ini telah berakhir. Silakan ajukan ulang slot pendaftaran jika kuota masih tersedia.',
                ];
            }

            $webhookPayload = (array) ($lockedPayment->webhook_payload ?? []);
            $existingLock = $webhookPayload['gateway_initiation_lock'] ?? null;
            if (is_array($existingLock) && ! empty($existingLock['started_at'])) {
                $startedAt = \Illuminate\Support\Carbon::parse($existingLock['started_at']);
                if ($startedAt->gt(now()->subSeconds(30))) {
                    return [
                        'status' => 'error',
                        'message' => 'Permintaan pembayaran sedang diproses. Silakan tunggu beberapa detik sebelum mencoba kembali.',
                    ];
                }
            }

            $webhookPayload['gateway_initiation_lock'] = [
                'token' => $claimToken,
                'provider' => $provider,
                'started_at' => now()->toIso8601String(),
            ];
            $lockedPayment->update(['webhook_payload' => $webhookPayload]);

            $team = $lockedPayment->team;
            $participant = $lockedPayment->auditionParticipant;
            $payerName = $team?->manager_name ?? $participant?->emergency_contact_name ?? $participant?->full_name ?? 'Peserta';
            $payerEmail = $team?->manager_email ?? $participant?->user?->email ?? 'peserta@persijadevelopment.id';
            $payerPhone = $team?->manager_whatsapp ?? $participant?->emergency_contact_phone ?? '081234567890';
            $baseOrderId = $lockedPayment->invoice_number;

            $expiryMinutes = $lockedPayment->expires_at
                ? max(5, (int) now()->diffInMinutes($lockedPayment->expires_at, false))
                : 1440;

            $gatewayFee = $this->calculateGatewayFee(
                (float) $lockedPayment->amount,
                $resolvedChannel['fee_category'] ?? null
            );
            $totalAmount = (float) $lockedPayment->amount + $gatewayFee;

            $savedChannel = $resolvedChannel['saved_label']
                ?? ($provider === 'xendit' ? 'Xendit Invoice' : 'Midtrans Snap (Multi-Channel)');

            $isReinitiation = $forceRegenerate
                || ! empty($lockedPayment->snap_token)
                || ! empty($lockedPayment->payment_url)
                || ! empty($lockedPayment->external_reference_id)
                || ($resolvedChannel !== null && $lockedPayment->payment_channel !== null && $lockedPayment->payment_channel !== $resolvedChannel['saved_label']);

            $providerOrderId = $isReinitiation
                ? $baseOrderId.'-'.substr((string) time(), -5).Str::upper(Str::random(3))
                : $baseOrderId;

            $payerDescription = $team
                ? 'Pendaftaran Tim '.$team->name.' - '.$lockedEvent->name
                : 'Pendaftaran Audisi '.$payerName.' - '.$lockedEvent->name;

            $channelsMode = SystemSetting::get('midtrans_channels_mode', 'all');
            $customChannels = array_values((array) SystemSetting::get('midtrans_active_channels', []));

            $enabledPayments = null;
            if ($provider === 'midtrans') {
                if ($resolvedChannel !== null) {
                    $enabledPayments = $resolvedChannel['enabled_payments'];
                } elseif ($channelsMode === 'custom' && ! empty($customChannels)) {
                    $enabledPayments = $customChannels;
                }
            }

            return [
                'status' => 'ready',
                'payment_id' => $lockedPayment->id,
                'invoice_number' => $lockedPayment->invoice_number,
                'base_order_id' => $baseOrderId,
                'provider_order_id' => $providerOrderId,
                'payer_name' => $payerName,
                'payer_email' => $payerEmail,
                'payer_phone' => $payerPhone,
                'payer_description' => $payerDescription,
                'expiry_minutes' => $expiryMinutes,
                'gateway_fee' => $gatewayFee,
                'total_amount' => $totalAmount,
                'saved_channel' => $savedChannel,
                'enabled_payments' => $enabledPayments,
            ];
        });

        if ($prepared['status'] === 'already_paid') {
            return $prepared['payment'];
        }

        if ($prepared['status'] === 'error') {
            throw ValidationException::withMessages([
                'payment' => $prepared['message'],
            ]);
        }

        // Langkah 2 (Di luar DB Transaction): Panggil API Midtrans / Xendit atau simulator.
        $gatewayResult = $this->executeGatewayInitiationRequest(
            provider: $provider,
            prepared: $prepared,
            resolvedChannel: $resolvedChannel
        );

        // Langkah 3 (Transaksi Singkat): Lepas claim lock dan simpan hasil token/url secara atomik.
        $final = DB::transaction(function () use ($payment, $provider, $claimToken, $prepared, $gatewayResult) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $webhookPayload = (array) ($lockedPayment->webhook_payload ?? []);

            if (($webhookPayload['gateway_initiation_lock']['token'] ?? null) === $claimToken) {
                unset($webhookPayload['gateway_initiation_lock']);
            }

            if (in_array($lockedPayment->status, ['paid', 'refund_required', 'refunded'], true)) {
                $lockedPayment->update(['webhook_payload' => $webhookPayload]);

                return [
                    'ok' => true,
                    'payment' => $lockedPayment->fresh(),
                ];
            }

            if (! $gatewayResult['ok']) {
                $lockedPayment->update(['webhook_payload' => $webhookPayload]);

                return [
                    'ok' => false,
                    'message' => $gatewayResult['message'],
                ];
            }

            $attemptedOrderIds = (array) ($webhookPayload['attempted_order_ids'] ?? []);
            foreach ([$gatewayResult['external_ref'], $gatewayResult['provider_order_id'], $prepared['base_order_id']] as $candidateId) {
                if (! empty($candidateId) && ! in_array($candidateId, $attemptedOrderIds, true)) {
                    $attemptedOrderIds[] = $candidateId;
                }
            }
            $webhookPayload['attempted_order_ids'] = array_values(array_unique(array_filter($attemptedOrderIds)));

            $lockedPayment->update([
                'method' => $provider,
                'payment_channel' => $prepared['saved_channel'],
                'gateway_fee' => $prepared['gateway_fee'],
                'total_amount' => $prepared['total_amount'],
                'external_reference_id' => $gatewayResult['external_ref'],
                'snap_token' => $gatewayResult['snap_token'],
                'payment_url' => $gatewayResult['payment_url'],
                'webhook_payload' => $webhookPayload,
            ]);

            return [
                'ok' => true,
                'payment' => $lockedPayment->fresh(),
            ];
        });

        if (! $final['ok']) {
            throw ValidationException::withMessages([
                'payment' => $final['message'],
            ]);
        }

        return $final['payment'];
    }

    /**
     * Eksekusi panggilan HTTP ke provider payment gateway di luar database transaction.
     *
     * @param  array<string, mixed>  $prepared
     * @param  array<string, mixed>|null  $resolvedChannel
     * @return array{ok: bool, message?: string, external_ref?: ?string, provider_order_id?: ?string, snap_token?: ?string, payment_url?: ?string}
     */
    protected function executeGatewayInitiationRequest(string $provider, array $prepared, ?array $resolvedChannel): array
    {
        if (config('payment.sandbox_simulator')) {
            $externalRef = strtoupper($provider).'-SIM-'.Str::upper(Str::random(10));

            return [
                'ok' => true,
                'external_ref' => $externalRef,
                'provider_order_id' => $prepared['provider_order_id'],
                'snap_token' => $provider === 'midtrans' ? 'SNAP-SIM-'.Str::uuid()->toString() : null,
                'payment_url' => $provider === 'xendit'
                    ? route('manager.payments', ['invoice' => $prepared['invoice_number']])
                    : null,
            ];
        }

        try {
            if ($provider === 'midtrans') {
                $midtransOrderId = $prepared['provider_order_id'];
                $snapPayload = [
                    'transaction_details' => [
                        'order_id' => $midtransOrderId,
                        'gross_amount' => (int) round($prepared['total_amount']),
                    ],
                    'customer_details' => [
                        'first_name' => $prepared['payer_name'],
                        'email' => $prepared['payer_email'],
                        'phone' => $prepared['payer_phone'],
                    ],
                    'expiry' => [
                        'unit' => 'minute',
                        'duration' => $prepared['expiry_minutes'],
                    ],
                    'callbacks' => [
                        'finish' => route('manager.payments', ['feedback' => 'finish']),
                        'unfinish' => route('manager.payments', ['feedback' => 'unfinish']),
                        'error' => route('manager.payments', ['feedback' => 'error']),
                    ],
                ];

                if (! empty($prepared['enabled_payments'])) {
                    $snapPayload['enabled_payments'] = $prepared['enabled_payments'];
                }

                $response = Http::withBasicAuth(self::midtransServerKey(), '')
                    ->acceptJson()
                    ->post(self::midtransSnapUrl(), $snapPayload);

                // Retry khusus duplicate order_id (tanpa pernah menghapus filter enabled_payments).
                if (! $response->successful()) {
                    $respBody = (string) $response->body();
                    if (str_contains($respBody, 'order_id') && (str_contains($respBody, 'already been taken') || str_contains($respBody, 'sudah digunakan') || str_contains($respBody, 'has already'))) {
                        $midtransOrderId = $prepared['base_order_id'].'-'.substr((string) time(), -5).Str::upper(Str::random(3));
                        $snapPayload['transaction_details']['order_id'] = $midtransOrderId;
                        $response = Http::withBasicAuth(self::midtransServerKey(), '')
                            ->acceptJson()
                            ->post(self::midtransSnapUrl(), $snapPayload);
                    }
                }

                if (! $response->successful()) {
                    \Illuminate\Support\Facades\Log::error('Midtrans Snap initiation failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'invoice' => $prepared['invoice_number'],
                        'order_id' => $midtransOrderId,
                    ]);

                    $errorMsg = $response->json('error_messages.0');
                    $channelSuffix = ! empty($snapPayload['enabled_payments'])
                        ? ' Silakan pilih saluran pembayaran lain atau hubungi panitia.'
                        : ' Silakan coba kembali atau gunakan transfer manual.';

                    return [
                        'ok' => false,
                        'message' => 'Gagal menghubungi penyedia pembayaran Midtrans'.($errorMsg ? ': '.$errorMsg.'.' : '.').$channelSuffix,
                    ];
                }

                $body = $response->json();

                return [
                    'ok' => true,
                    'external_ref' => $midtransOrderId,
                    'provider_order_id' => $midtransOrderId,
                    'snap_token' => $body['token'] ?? null,
                    'payment_url' => $body['redirect_url'] ?? null,
                ];
            }

            if ($provider === 'xendit') {
                $xenditExternalId = $prepared['provider_order_id'];
                $payload = [
                    'external_id' => $xenditExternalId,
                    'amount' => (int) round($prepared['total_amount']),
                    'payer_email' => $prepared['payer_email'],
                    'description' => $prepared['payer_description'],
                    'invoice_duration' => $prepared['expiry_minutes'] * 60,
                ];

                if ($resolvedChannel !== null) {
                    $payload['payment_methods'] = $resolvedChannel['enabled_payments'];
                }

                $response = Http::withBasicAuth(self::xenditSecretKey(), '')
                    ->acceptJson()
                    ->post(config('payment.xendit.invoice_url'), $payload);

                if (! $response->successful()) {
                    \Illuminate\Support\Facades\Log::error('Xendit invoice initiation failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'invoice' => $prepared['invoice_number'],
                        'external_id' => $xenditExternalId,
                    ]);

                    return [
                        'ok' => false,
                        'message' => 'Gagal menghubungi penyedia pembayaran Xendit. Silakan pilih saluran lain atau coba kembali.',
                    ];
                }

                $body = $response->json();

                return [
                    'ok' => true,
                    'external_ref' => $body['id'] ?? $prepared['invoice_number'],
                    'provider_order_id' => $xenditExternalId,
                    'snap_token' => null,
                    'payment_url' => $body['invoice_url'] ?? null,
                ];
            }

            return [
                'ok' => false,
                'message' => 'Penyedia pembayaran tidak dikenali.',
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Gateway initiation exception: '.$e->getMessage(), [
                'provider' => $provider,
                'invoice' => $prepared['invoice_number'],
            ]);

            return [
                'ok' => false,
                'message' => 'Terjadi gangguan jaringan saat menghubungi penyedia pembayaran. Silakan coba beberapa saat lagi.',
            ];
        }
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
                'amount' => (int) round((float) $payment->total_amount),
                'payment_channel' => $payment->payment_channel ?: 'QRIS',
            ], self::xenditCallbackToken());
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
        if (! self::isProviderEnabled('midtrans') || ! self::midtransServerKey() || in_array($payment->status, ['paid', 'refund_required', 'refunded'], true)) {
            return $payment;
        }

        $attempted = (array) ($payment->webhook_payload['attempted_order_ids'] ?? []);
        $orderIds = array_values(array_unique(array_filter(array_merge(
            [$payment->external_reference_id],
            $attempted,
            [$payment->invoice_number]
        ))));

        $baseUrl = self::midtransIsProduction()
            ? 'https://api.midtrans.com/v2/'
            : 'https://api.sandbox.midtrans.com/v2/';

        foreach ($orderIds as $orderId) {
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

                    if ($isSettled) {
                        return $this->processVerifiedGatewayCallback(
                            invoiceNumber: $orderId,
                            provider: 'midtrans',
                            channel: $payload['payment_type'] ?? null,
                            externalRef: $payload['transaction_id'] ?? null,
                            callbackAmount: (string) ($payload['gross_amount'] ?? $payment->total_amount),
                            isSettled: true,
                            isExpiredSignal: false,
                            rawPayload: $payload
                        );
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Sync Midtrans status failed for order '.$orderId.': '.$e->getMessage());
            }
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
        $expectedToken = self::xenditCallbackToken();

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
                ->orWhereJsonContains('webhook_payload->attempted_order_ids', $invoiceNumber)
                ->first();

            if (! $payment && preg_match('/^(INV-\d{4}-(?:EVT|AUD)\d+-\d+)/', $invoiceNumber, $matches)) {
                $payment = Payment::query()->where('invoice_number', $matches[1])->first();
            }

            if (! $payment) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Payment::class, [$invoiceNumber]);
            }

            $lockedEvent = Event::query()->whereKey($payment->event_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedTeam = $lockedPayment->team_id ? $lockedPayment->team()->lockForUpdate()->first() : null;
            $lockedParticipant = $lockedPayment->audition_participant_id ? $lockedPayment->auditionParticipant()->lockForUpdate()->first() : null;

            // Validasi provider: bila tagihan sudah terikat ke provider tertentu (non-null), callback dari provider lain ditolak.
            if ($lockedPayment->method !== null && $lockedPayment->method !== $provider) {
                throw new AccessDeniedHttpException("Provider callback ({$provider}) tidak sesuai dengan metode aktif invoice ({$lockedPayment->method}).");
            }

            if ($lockedPayment->status === 'waiting_verification') {
                throw new AccessDeniedHttpException('Tagihan sedang dalam antrean verifikasi transfer manual.');
            }

            // Idempotency: jika sudah paid, refund_required, atau refunded, abaikan sinyal duplikat.
            if (in_array($lockedPayment->status, ['paid', 'refund_required', 'refunded'], true)) {
                return $lockedPayment;
            }

            $tolerance = max(0.0, (float) config('payment.amount_tolerance', 1.0));
            $expectedTotal = (float) $lockedPayment->total_amount;

            if ($callbackAmount !== null) {
                if (! is_numeric($callbackAmount)) {
                    throw new AccessDeniedHttpException('Format nominal callback pembayaran tidak valid.');
                }

                $cbFloat = (float) $callbackAmount;

                // Underpayment (termasuk bypass biaya layanan/MDR) wajib ditolak.
                if ($cbFloat < ($expectedTotal - $tolerance)) {
                    throw new AccessDeniedHttpException('Nominal callback pembayaran kurang dari total tagihan invoice.');
                }

                // Overpayment melebihi toleransi ditandai refund_required pada sinyal settlement agar direkonsiliasi Finance.
                if ($cbFloat > ($expectedTotal + $tolerance)) {
                    if (! $isSettled) {
                        throw new AccessDeniedHttpException('Nominal callback pembayaran melebihi total tagihan invoice.');
                    }

                    $previousStatus = $lockedPayment->status;
                    $reason = sprintf(
                        'Overpayment terdeteksi: diterima Rp %s, tagihan resmi Rp %s. Memerlukan review/refund kelebihan dana.',
                        number_format($cbFloat, 0, ',', '.'),
                        number_format($expectedTotal, 0, ',', '.')
                    );

                    $lockedPayment->update([
                        'method' => $provider,
                        'payment_channel' => $channel ?: $lockedPayment->payment_channel,
                        'external_reference_id' => $externalRef ?: $lockedPayment->external_reference_id,
                        'status' => 'refund_required',
                        'paid_at' => now(),
                        'rejection_reason' => $reason,
                        'webhook_payload' => $rawPayload,
                    ]);

                    AuditLog::record(
                        entity: $lockedPayment,
                        action: 'payment.gateway.overpayment_flagged',
                        fromStatus: $previousStatus,
                        toStatus: 'refund_required',
                        reason: $reason,
                        metadata: [
                            'provider' => $provider,
                            'external_reference_id' => $externalRef,
                            'expected_total' => $expectedTotal,
                            'received_amount' => $cbFloat,
                        ],
                    );

                    return $lockedPayment->fresh();
                }
            }

            if ($isSettled) {
                $previousStatus = $lockedPayment->status;
                $wasExpired = $lockedPayment->status === 'expired'
                    || ($lockedTeam && $lockedTeam->status === 'expired')
                    || ($lockedParticipant && $lockedParticipant->status === 'cancelled');

                if ($wasExpired) {
                    $this->quotaExpiration->sweepExpiredForEvent($lockedEvent);
                    $occupied = $lockedEvent->occupiedSlotsCount();

                    // Over-Quota Guard: dilarang melampaui kuota meskipun ada late webhook settlement
                    $limit = $lockedEvent->max_participants ?: $lockedEvent->max_teams;
                    if (! $lockedEvent->isUnlimitedQuota() && $limit !== null && $occupied >= $limit) {
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

                    if ($lockedTeam) {
                        $lockedTeam->update([
                            'status' => 'active',
                            'slot_expires_at' => null,
                        ]);
                    }
                    if ($lockedParticipant) {
                        $wasCancelled = $lockedParticipant->status === 'cancelled';
                        $lockedParticipant->update([
                            'status' => 'active',
                            'slot_expires_at' => null,
                        ]);
                        if ($wasCancelled && $lockedParticipant->event_session_id) {
                            EventSession::whereKey($lockedParticipant->event_session_id)->increment('occupied_slots');
                        }
                    }

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

                    if ($lockedTeam && ! in_array($lockedTeam->status, ['locked_roster', 'disqualified'], true)) {
                        $lockedTeam->update([
                            'status' => 'active',
                            'slot_expires_at' => null,
                        ]);
                    }
                    if ($lockedParticipant) {
                        $lockedParticipant->update([
                            'status' => 'active',
                            'slot_expires_at' => null,
                        ]);
                    }

                    $lockedEvent->syncQuotaStatus();
                }

                if ($lockedTeam) {
                    app(EmailNotificationService::class)->sendPaymentConfirmed($lockedTeam, $lockedPayment);
                } elseif ($lockedParticipant) {
                    app(EmailNotificationService::class)->sendAuditionPaymentConfirmed($lockedParticipant, $lockedPayment);
                }

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

                if ($lockedTeam && $lockedTeam->status === 'pending_payment') {
                    $lockedTeam->update(['status' => 'expired']);
                }

                if ($lockedParticipant && $lockedParticipant->status === 'pending_payment') {
                    $lockedParticipant->update(['status' => 'cancelled']);
                    if ($lockedParticipant->event_session_id) {
                        EventSession::whereKey($lockedParticipant->event_session_id)
                            ->where('occupied_slots', '>', 0)
                            ->decrement('occupied_slots');
                    }
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
            $this->quotaExpiration->sweepExpiredForEvent($lockedEvent);

            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedTeam = $lockedPayment->team_id ? $lockedPayment->team()->lockForUpdate()->first() : null;
            $lockedParticipant = $lockedPayment->audition_participant_id ? $lockedPayment->auditionParticipant()->lockForUpdate()->first() : null;

            if ($lockedPayment->status === 'paid') {
                return $lockedPayment;
            }

            if ($lockedPayment->status !== 'waiting_verification') {
                throw ValidationException::withMessages([
                    'payment' => 'Hanya pembayaran berstatus Menunggu Verifikasi yang dapat disetujui.',
                ]);
            }

            $limit = $lockedEvent->isIndividual()
                ? $lockedEvent->max_participants
                : $lockedEvent->max_teams;

            if (! $lockedEvent->isUnlimitedQuota() && $limit !== null) {
                $occupiedExcludingSelf = $lockedEvent->isIndividual()
                    ? $lockedEvent->auditionParticipants()
                        ->whereIn('status', ['registered', 'confirmed', 'active', 'passed'])
                        ->when($lockedParticipant, fn ($q) => $q->whereKeyNot($lockedParticipant->id))
                        ->count()
                    : $lockedEvent->teams()
                        ->occupyingQuotaAt(now())
                        ->when($lockedTeam, fn ($q) => $q->whereKeyNot($lockedTeam->id))
                        ->count();

                if ($occupiedExcludingSelf >= $limit) {
                    $reason = 'Pembayaran manual diterima setelah kuota kompetisi penuh.';
                    $lockedPayment->update([
                        'status' => 'refund_required',
                        'verified_by' => $verifier->id,
                        'verified_at' => now(),
                        'paid_at' => now(),
                        'rejection_reason' => $reason,
                    ]);

                    if ($lockedTeam && ! in_array($lockedTeam->status, ['active', 'locked_roster', 'disqualified'], true)) {
                        $lockedTeam->update([
                            'status' => 'expired',
                            'slot_expires_at' => null,
                        ]);
                    }

                    if ($lockedParticipant && ! in_array($lockedParticipant->status, ['registered', 'confirmed', 'active', 'passed'], true)) {
                        $lockedParticipant->update([
                            'status' => 'cancelled',
                            'slot_expires_at' => null,
                        ]);
                        if ($lockedParticipant->event_session_id) {
                            EventSession::whereKey($lockedParticipant->event_session_id)
                                ->where('occupied_slots', '>', 0)
                                ->decrement('occupied_slots');
                        }
                    }

                    $lockedEvent->syncQuotaStatus();

                    AuditLog::record(
                        entity: $lockedPayment,
                        action: 'payment.manual.over_quota_refund_required',
                        actor: $verifier,
                        fromStatus: 'waiting_verification',
                        toStatus: 'refund_required',
                        reason: $reason,
                    );

                    return $lockedPayment->fresh();
                }
            }

            $now = now();

            $lockedPayment->update([
                'status' => 'paid',
                'verified_by' => $verifier->id,
                'verified_at' => $now,
                'paid_at' => $now,
                'rejection_reason' => null,
            ]);

            if ($lockedTeam && ! in_array($lockedTeam->status, ['locked_roster', 'disqualified'], true)) {
                $lockedTeam->update([
                    'status' => 'active',
                    'slot_expires_at' => null,
                ]);
            }

            if ($lockedParticipant) {
                $wasCancelled = $lockedParticipant->status === 'cancelled';
                $lockedParticipant->update([
                    'status' => 'active',
                    'slot_expires_at' => null,
                ]);
                if ($wasCancelled && $lockedParticipant->event_session_id) {
                    EventSession::whereKey($lockedParticipant->event_session_id)->increment('occupied_slots');
                }
            }

            $lockedEvent->syncQuotaStatus();

            AuditLog::record(
                entity: $lockedPayment,
                action: 'payment.manual.approved',
                actor: $verifier,
                fromStatus: 'waiting_verification',
                toStatus: 'paid',
            );

            if ($lockedTeam) {
                app(EmailNotificationService::class)->sendPaymentConfirmed($lockedTeam, $lockedPayment);
            } elseif ($lockedParticipant) {
                app(EmailNotificationService::class)->sendAuditionPaymentConfirmed($lockedParticipant, $lockedPayment);
            }

            return $lockedPayment->fresh();
        });
    }

    public function rejectManualPayment(Payment $payment, User $verifier, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $verifier, $reason) {
            $lockedEvent = Event::query()->whereKey($payment->event_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedTeam = $lockedPayment->team_id ? $lockedPayment->team()->lockForUpdate()->first() : null;
            $lockedParticipant = $lockedPayment->audition_participant_id ? $lockedPayment->auditionParticipant()->lockForUpdate()->first() : null;

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

            if ($lockedTeam && $lockedTeam->status === 'pending_payment') {
                $lockedTeam->update([
                    'slot_expires_at' => $newExpiresAt,
                ]);
            }

            if ($lockedParticipant && $lockedParticipant->status === 'pending_payment') {
                $lockedParticipant->update([
                    'slot_expires_at' => $newExpiresAt,
                ]);
            }

            if ($lockedTeam) {
                app(EmailNotificationService::class)->sendPaymentRejected($lockedTeam, $lockedPayment, $reason);
            }

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
    public function markRefunded(
        Payment $payment,
        User $verifier,
        string $refundNotes,
        ?float $refundAmount = null,
        ?string $refundReference = null,
        ?string $refundMethod = null
    ): Payment {
        return DB::transaction(function () use ($payment, $verifier, $refundNotes, $refundAmount, $refundReference, $refundMethod) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedTeam = $lockedPayment->team_id ? $lockedPayment->team()->lockForUpdate()->first() : null;
            $lockedParticipant = $lockedPayment->audition_participant_id ? $lockedPayment->auditionParticipant()->lockForUpdate()->first() : null;

            if ($lockedPayment->status === 'refunded') {
                return $lockedPayment;
            }

            if ($lockedPayment->status !== 'refund_required') {
                throw ValidationException::withMessages([
                    'payment' => 'Hanya transaksi berstatus Perlu Refund (refund_required) yang dapat ditandai selesai refund.',
                ]);
            }

            $cleanNotes = trim($refundNotes);
            $now = now();
            $resolvedAmount = $refundAmount !== null ? $refundAmount : (float) $lockedPayment->total_amount;
            $resolvedMethod = $refundMethod ? trim($refundMethod) : 'manual_transfer';
            $resolvedReference = $refundReference ? trim($refundReference) : null;

            $lockedPayment->update([
                'status' => 'refunded',
                'verified_by' => $verifier->id,
                'verified_at' => $now,
                'refund_amount' => $resolvedAmount,
                'refund_reference' => $resolvedReference,
                'refund_method' => $resolvedMethod,
                'refund_notes' => $cleanNotes,
                'refunded_at' => $now,
                'rejection_reason' => 'REFUND SELESAI: '.$cleanNotes,
            ]);

            if ($lockedTeam) {
                $lockedTeam->update([
                    'status' => 'expired',
                ]);
            }

            if ($lockedParticipant) {
                $lockedParticipant->update([
                    'status' => 'cancelled',
                ]);
            }

            AuditLog::record(
                entity: $lockedPayment,
                action: 'payment.refund.completed',
                actor: $verifier,
                fromStatus: 'refund_required',
                toStatus: 'refunded',
                reason: $cleanNotes,
            );

            if ($lockedTeam) {
                app(EmailNotificationService::class)->sendPaymentRefunded($lockedTeam, $lockedPayment, $cleanNotes);
            }

            return $lockedPayment->fresh();
        });
    }
}
