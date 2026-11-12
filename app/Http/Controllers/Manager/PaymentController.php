<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesActiveTeam;
use App\Http\Requests\Manager\InitiateGatewayPaymentRequest;
use App\Http\Requests\Manager\UploadPaymentProofRequest;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Services\PaymentService;
use App\Services\QuotaExpirationService;
use App\Services\TeamRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    use ResolvesActiveTeam;

    public function index(Request $request, QuotaExpirationService $quotaExpiration): Response
    {
        $ctx = $this->resolveManagerContext($request);
        /** @var Team|null $team */
        $team = $ctx['activeTeam'];

        $activePayment = null;
        $paymentHistory = [];

        if ($team) {
            DB::transaction(function () use ($team, $quotaExpiration) {
                $lockedEvent = $team->event()->lockForUpdate()->first();
                if ($lockedEvent) {
                    $quotaExpiration->sweepExpiredForEvent($lockedEvent);
                }
            });

            $team->refresh()->load('event');
            $ctx['teamPayload'] = $this->formatTeamForManager($team);

            $payments = $team->payments()->orderByDesc('created_at')->get();
            $latest = $payments->first();

            if ($latest) {
                if ($latest->status === 'unpaid'
                    && empty($latest->snap_token)
                    && SystemSetting::get('payment_method_mode') === 'midtrans'
                    && ! config('payment.sandbox_simulator')
                ) {
                    try {
                        $paymentService = app(PaymentService::class);
                        $latest = $paymentService->initiateGatewayPayment(
                            payment: $latest,
                            provider: 'midtrans'
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Auto initiate Midtrans on payment page load failed: ' . $e->getMessage());
                    }
                }
                $activePayment = $this->formatPayment($latest);
            }

            $paymentHistory = $payments->map(fn (Payment $p) => $this->formatPayment($p))->values();
        }

        $bankAccounts = SystemSetting::get('manual_bank_accounts');
        if (empty($bankAccounts)) {
            $defaultBank = config('payment.manual_bank');
            $bankAccounts = $defaultBank ? [$defaultBank] : [];
        }

        return Inertia::render('Manager/Payments', [
            'team' => $ctx['teamPayload'],
            'teamsList' => $ctx['teamsList'],
            'payment' => $activePayment,
            'paymentHistory' => $paymentHistory,
            'paymentMethodMode' => SystemSetting::get('payment_method_mode', 'midtrans'),
            'bankAccounts' => $bankAccounts,
            'midtransFeeConfig' => [
                'fee_borne_by' => SystemSetting::get('fee_borne_by', 'team'),
                'va_fee' => (int) SystemSetting::get('midtrans_va_fee', 4000),
                'qris_fee_percent' => (float) SystemSetting::get('midtrans_qris_fee_percent', 0.7),
                'ewallet_fee_percent' => (float) SystemSetting::get('midtrans_ewallet_fee_percent', 1.5),
                'cc_fee_percent' => (float) SystemSetting::get('midtrans_cc_fee_percent', 2.9),
                'cc_fee_fixed' => (int) SystemSetting::get('midtrans_cc_fee_fixed', 2000),
            ],
            'midtransClientKey' => PaymentService::midtransClientKey(),
            'midtransSnapJsUrl' => PaymentService::midtransSnapJsUrl(),
            'midtransIsProduction' => PaymentService::midtransIsProduction(),
            'sandboxSimulatorEnabled' => (bool) config('payment.sandbox_simulator'),
        ]);
    }

    public function initiateGateway(
        InitiateGatewayPaymentRequest $request,
        Payment $payment,
        PaymentService $paymentService
    ): RedirectResponse {
        $payment->loadMissing('team');
        $this->authorize('pay', $payment);

        $validated = $request->validated();
        $paymentService->initiateGatewayPayment(
            payment: $payment,
            provider: $validated['provider'],
            channel: $validated['payment_channel'] ?? null,
            forceRegenerate: (bool) ($validated['force_regenerate'] ?? false)
        );

        return redirect()->back()
            ->with('success', 'Instruksi pembayaran instan berhasil dibuat. Silakan selesaikan pembayaran sebelum waktu kedaluwarsa.');
    }

    public function simulateSandbox(
        Request $request,
        Payment $payment,
        PaymentService $paymentService
    ): RedirectResponse {
        if (! config('payment.sandbox_simulator')) {
            abort(403, 'Simulator pembayaran dinonaktifkan di lingkungan produksi.');
        }

        $payment->loadMissing('team');
        $this->authorize('pay', $payment);

        $updated = $paymentService->simulateSandboxSettlement($payment);

        if ($updated->status === 'refund_required') {
            return redirect()->back()
                ->with('warning', 'Pembayaran diterima terlambat setelah kuota penuh. Status ditandai Perlu Refund untuk tindak lanjut Finance.');
        }

        return redirect()->route('manager.players')
            ->with('success', 'Pembayaran berhasil dikonfirmasi otomatis! Status tim Anda kini AKTIF dan pengisian daftar pemain serta dokumen telah dibuka.');
    }

    public function uploadManualProof(
        UploadPaymentProofRequest $request,
        Payment $payment,
        PaymentService $paymentService
    ): RedirectResponse {
        $payment->loadMissing('team');
        $this->authorize('pay', $payment);

        $paymentService->uploadManualProof(
            payment: $payment,
            validated: $request->validated(),
            proofFile: $request->file('proof_file')
        );

        return redirect()->back()
            ->with('success', 'Bukti transfer berhasil diunggah dan masuk antrean verifikasi staf Finance. Slot kuota Anda tetap aman selama proses pemeriksaan.');
    }

    public function reclaimSlot(
        Request $request,
        Team $team,
        TeamRegistrationService $registrationService
    ): RedirectResponse {
        $this->authorize('update', $team);

        $registrationService->reclaimExpiredSlot($team);

        return redirect()->back()
            ->with('success', 'Slot pendaftaran berhasil diamankan kembali! Tagihan baru telah diterbitkan.');
    }

    protected function formatPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'invoice_number' => $payment->invoice_number,
            'amount' => (float) $payment->amount,
            'gateway_fee' => (float) $payment->gateway_fee,
            'total_amount' => (float) $payment->total_amount,
            'method' => $payment->method,
            'payment_channel' => $payment->payment_channel,
            'external_reference_id' => $payment->external_reference_id,
            'snap_token' => $payment->snap_token,
            'payment_url' => $payment->payment_url,
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'sender_bank_name' => $payment->sender_bank_name,
            'sender_account_holder' => $payment->sender_account_holder,
            'sender_account_number' => $payment->sender_account_number,
            'transfer_date' => $payment->transfer_date?->toDateString(),
            'has_proof' => ! empty($payment->proof_file_path),
            'proof_url' => $payment->proof_file_path
                ? route('private.payment-proof', ['payment' => $payment->id])
                : null,
            'proof_is_pdf' => $payment->proof_file_path
                ? strtolower(pathinfo($payment->proof_file_path, PATHINFO_EXTENSION)) === 'pdf'
                : false,
            'status' => $payment->status,
            'rejection_reason' => $payment->rejection_reason,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'verified_at' => $payment->verified_at?->toIso8601String(),
        ];
    }
}

