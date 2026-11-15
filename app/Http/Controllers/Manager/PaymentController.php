<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesActiveTeam;
use App\Http\Requests\Manager\InitiateGatewayPaymentRequest;
use App\Http\Requests\Manager\UploadPaymentProofRequest;
use App\Models\AuditionParticipant;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\QuotaExpirationService;
use App\Services\TeamRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    use ResolvesActiveTeam;

    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function index(Request $request, QuotaExpirationService $quotaExpiration): Response
    {
        $user = $request->user();

        if ($request->filled('payment_id') && ! $request->filled('team_id') && ! $request->filled('participant_id')) {
            $requestedPaymentModel = Payment::query()
                ->where('id', (int) $request->query('payment_id'))
                ->where(function ($q) use ($user) {
                    $q->whereHas('team', fn ($tq) => $tq->where('user_id', $user->id))
                        ->orWhereHas('auditionParticipant', fn ($pq) => $pq->where('user_id', $user->id));
                })
                ->first();

            if ($requestedPaymentModel) {
                if ($requestedPaymentModel->audition_participant_id) {
                    $request->query->set('participant_id', (string) $requestedPaymentModel->audition_participant_id);
                } elseif ($requestedPaymentModel->team_id) {
                    $request->query->set('team_id', (string) $requestedPaymentModel->team_id);
                }
            }
        }

        if ($request->filled('team_id')) {
            $requestedTeamId = (int) $request->query('team_id');
            $ownsTeam = Team::query()
                ->where('id', $requestedTeamId)
                ->where('user_id', $user->id)
                ->exists();
            if ($ownsTeam) {
                $request->session()->put('active_manager_team_id', $requestedTeamId);
                $request->session()->put('active_payment_context', 'team');
            }
        }

        $ctx = $this->resolveManagerContext($request);
        /** @var Team|null $team */
        $team = $ctx['activeTeam'];

        $auditionParticipants = AuditionParticipant::query()
            ->where('user_id', $user->id)
            ->with(['event', 'eventSession', 'latestPayment'])
            ->latest()
            ->get();

        $isActiveTeamPaid = $team
            ? ($team->latestPayment?->status === 'paid' || (float) ($team->event?->registration_fee ?? 0) <= 0)
            : false;
        $unpaidParticipant = $auditionParticipants->first(
            fn (AuditionParticipant $p) => (! $p->isPaid() || $p->latestPayment?->status === 'rejected') && $p->latestPayment !== null
        );

        $selectedParticipant = null;
        if ($request->filled('participant_id')) {
            $selectedParticipant = $auditionParticipants->firstWhere('id', (int) $request->query('participant_id'));
            if ($selectedParticipant) {
                $request->session()->put('active_payment_context', 'participant');
                $request->session()->put('active_manager_participant_id', $selectedParticipant->id);
            }
        } elseif (($request->boolean('team_context') || $request->filled('team_id')) && $team) {
            $request->session()->put('active_payment_context', 'team');
        } elseif (! $team || ($request->session()->get('active_payment_context') === 'participant' && $auditionParticipants->isNotEmpty())) {
            $savedParticipantId = (int) $request->session()->get('active_manager_participant_id', 0);
            $selectedParticipant = $auditionParticipants->firstWhere('id', $savedParticipantId)
                ?? $unpaidParticipant
                ?? $auditionParticipants->first();
        } elseif ($isActiveTeamPaid && $unpaidParticipant) {
            $selectedParticipant = $unpaidParticipant;
            $request->session()->put('active_payment_context', 'participant');
            $request->session()->put('active_manager_participant_id', $selectedParticipant->id);
        }

        $activePayment = null;
        $paymentHistory = [];
        $participantPayload = null;
        $billingType = $selectedParticipant ? 'audition_participant' : 'team';

        $paymentRelations = [
            'team.manager',
            'team.event',
            'auditionParticipant.user',
            'auditionParticipant.event',
            'auditionParticipant.eventSession',
            'event',
            'verifier',
        ];

        if ($selectedParticipant) {
            DB::transaction(function () use ($selectedParticipant, $quotaExpiration) {
                $lockedEvent = $selectedParticipant->event()->lockForUpdate()->first();
                if ($lockedEvent) {
                    $quotaExpiration->sweepExpiredForEvent($lockedEvent);
                }
            });

            $selectedParticipant->refresh()->load(['event', 'eventSession', 'latestPayment']);
            $participantPayload = $this->formatAuditionParticipant($selectedParticipant);
            $payments = $selectedParticipant->payments()->with($paymentRelations)->orderByDesc('created_at')->get();
        } elseif ($team) {
            DB::transaction(function () use ($team, $quotaExpiration) {
                $lockedEvent = $team->event()->lockForUpdate()->first();
                if ($lockedEvent) {
                    $quotaExpiration->sweepExpiredForEvent($lockedEvent);
                }
            });

            $team->refresh()->load('event');
            $ctx['teamPayload'] = $this->formatTeamForManager($team);
            $payments = $team->payments()->with($paymentRelations)->orderByDesc('created_at')->get();
        } else {
            $payments = collect();
        }

        $latest = $payments->first();
        if ($latest) {
            $requestedPaymentId = (int) $request->query('payment_id', 0);
            $selectingLatest = $requestedPaymentId === 0 || $requestedPaymentId === $latest->id;

            $paymentService = app(PaymentService::class);
            $paidMessage = $selectedParticipant
                ? "Pembayaran pendaftaran peserta {$selectedParticipant->full_name} telah terverifikasi lunas di Midtrans!"
                : 'Pembayaran sebelumnya telah terverifikasi lunas di Midtrans! Status tim Anda kini telah aktif.';

            if ($latest->status === 'unpaid'
                && ($request->has('order_id') || $request->has('feedback') || $request->has('transaction_status') || $request->has('check_status'))
                && PaymentService::isProviderEnabled('midtrans')
            ) {
                $latest = $paymentService->syncMidtransTransactionStatus($latest);
                if ($latest->status === 'paid') {
                    session()->flash('success', $paidMessage);
                    if ($selectedParticipant) {
                        $selectedParticipant->refresh()->load(['event', 'eventSession', 'latestPayment']);
                        $participantPayload = $this->formatAuditionParticipant($selectedParticipant);
                    }
                } elseif ($request->has('check_status') && $latest->status === 'unpaid') {
                    session()->flash('info', 'Transaksi belum terkonfirmasi lunas di sistem Midtrans. Silakan selesaikan pembayaran sesuai petunjuk.');
                }
            }

            $defaultProvider = PaymentService::activeGatewayProvider();
            if ($latest->status === 'unpaid'
                && empty($latest->snap_token)
                && empty($latest->payment_url)
                && PaymentService::isGatewayMode()
                && PaymentService::isProviderEnabled($defaultProvider)
                && ! config('payment.sandbox_simulator')
            ) {
                try {
                    $latest = $paymentService->initiateGatewayPayment(
                        payment: $latest,
                        provider: $defaultProvider
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Auto initiate gateway on payment page load failed: '.$e->getMessage());
                }
            }

            $activePaymentModel = $selectingLatest
                ? $latest
                : ($payments->firstWhere('id', $requestedPaymentId) ?? $latest);

            $activePayment = $this->formatPayment($activePaymentModel);
            $paymentHistory = $payments->map(fn (Payment $p) => $this->formatPayment($p))->values();
        }

        $participantsList = $auditionParticipants->map(fn (AuditionParticipant $p) => [
            'id' => $p->id,
            'full_name' => $p->full_name,
            'registration_number' => $p->registration_number,
            'event_name' => $p->event?->name,
            'age_category' => $p->event?->age_category,
            'is_event_completed' => $p->event?->status === 'completed',
            'is_paid' => $p->isPaid(),
            'payment_status' => $p->latestPayment?->status ?? ($p->isPaid() ? 'paid' : 'unpaid'),
        ])->values();

        $allTeamIds = collect($ctx['teamsList'])->pluck('id')->all();
        $allParticipantIds = $auditionParticipants->pluck('id')->all();
        $allAccountPayments = Payment::query()
            ->where(function ($q) use ($allTeamIds, $allParticipantIds) {
                $q->whereIn('team_id', $allTeamIds ?: [0])
                    ->orWhereIn('audition_participant_id', $allParticipantIds ?: [0]);
            })
            ->with($paymentRelations)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Payment $p) => $this->formatPayment($p))
            ->values();

        $bankAccounts = SystemSetting::get('manual_bank_accounts');
        if (empty($bankAccounts)) {
            $defaultBank = config('payment.manual_bank');
            $bankAccounts = $defaultBank ? [$defaultBank] : [];
        }

        return Inertia::render('Manager/Payments', [
            'billingType' => $billingType,
            'team' => $ctx['teamPayload'],
            'teamsList' => $ctx['teamsList'],
            'auditionParticipant' => $participantPayload,
            'participantsList' => $participantsList,
            'payment' => $activePayment,
            'paymentHistory' => $paymentHistory,
            'accountPayments' => $allAccountPayments,
            'paymentMethodMode' => PaymentService::activeMode(),
            'activeGatewayProvider' => PaymentService::activeGatewayProvider(),
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
        $payment->loadMissing(['team', 'auditionParticipant']);
        $this->authorize('pay', $payment);

        $validated = $request->validated();
        $redirectParams = $payment->audition_participant_id
            ? ['participant_id' => $payment->audition_participant_id]
            : [];

        // 1. Cek status asli ke Midtrans terlebih dahulu sebelum melakukan regenerasi atau pembayaran baru.
        if ($validated['provider'] === 'midtrans' && PaymentService::isProviderEnabled('midtrans')) {
            $payment = $paymentService->syncMidtransTransactionStatus($payment);

            if ($payment->status === 'paid') {
                return redirect()->route('manager.payments', $redirectParams)
                    ->with('success', $payment->audition_participant_id
                        ? 'Pembayaran pendaftaran peserta telah terverifikasi lunas di Midtrans!'
                        : 'Pembayaran sebelumnya telah terverifikasi lunas di Midtrans! Status tim Anda kini telah aktif.');
            }

            if ($payment->status === 'refund_required') {
                return redirect()->route('manager.payments', $redirectParams)
                    ->with('warning', 'Pembayaran terdeteksi namun slot kuota kompetisi telah habis. Tagihan dialihkan ke status Perlu Refund untuk ditindaklanjuti panitia.');
            }
        }

        if ($payment->status === 'paid') {
            return redirect()->route('manager.payments', $redirectParams)
                ->with('success', $payment->audition_participant_id
                    ? 'Tagihan ini telah berstatus lunas.'
                    : 'Tagihan ini telah berstatus lunas. Tim Anda telah aktif.');
        }

        $paymentService->initiateGatewayPayment(
            payment: $payment,
            provider: $validated['provider'],
            channel: $validated['payment_channel'] ?? null,
            forceRegenerate: (bool) ($validated['force_regenerate'] ?? false)
        );

        $message = ! empty($validated['force_regenerate'])
            ? 'Metode pembayaran berhasil diatur ulang. Silakan selesaikan pembayaran.'
            : 'Instruksi pembayaran instan berhasil dibuat. Silakan selesaikan pembayaran sebelum waktu kedaluwarsa.';

        return redirect()->back()->with('success', $message);
    }

    public function simulateSandbox(
        Request $request,
        Payment $payment,
        PaymentService $paymentService
    ): RedirectResponse {
        if (! config('payment.sandbox_simulator')) {
            abort(403, 'Simulator pembayaran dinonaktifkan di lingkungan produksi.');
        }

        $payment->loadMissing(['team', 'auditionParticipant']);
        $this->authorize('pay', $payment);

        $updated = $paymentService->simulateSandboxSettlement($payment);

        if ($updated->status === 'refund_required') {
            return redirect()->back()
                ->with('warning', 'Pembayaran diterima terlambat setelah kuota penuh. Status ditandai Perlu Refund untuk tindak lanjut Finance.');
        }

        if ($payment->audition_participant_id) {
            return redirect()->route('manager.dashboard')
                ->with('success', 'Pembayaran berhasil dikonfirmasi otomatis! Status peserta '.($payment->auditionParticipant?->full_name ?? '').' kini AKTIF.');
        }

        return redirect()->route('manager.players')
            ->with('success', 'Pembayaran berhasil dikonfirmasi otomatis! Status tim Anda kini AKTIF dan pengisian daftar pemain serta dokumen telah dibuka.');
    }

    public function uploadManualProof(
        UploadPaymentProofRequest $request,
        Payment $payment,
        PaymentService $paymentService
    ): RedirectResponse {
        $payment->loadMissing(['team', 'auditionParticipant']);
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

    public function downloadInvoice(Payment $payment, InvoicePdfService $pdfService): HttpResponse
    {
        $this->authorize('view', $payment);

        $pdf = $pdfService->generateInvoicePdf($payment);

        return $pdf->download($pdfService->filename($payment));
    }

    protected function formatPayment(Payment $payment): array
    {
        $invoiceMeta = $this->invoiceService->resolveInvoiceData($payment);

        return [
            'id' => $payment->id,
            'team_id' => $payment->team_id,
            'audition_participant_id' => $payment->audition_participant_id,
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
            'invoice_url' => route('manager.payments.invoice', ['payment' => $payment->id]),
            'invoice_meta' => $invoiceMeta,
        ];
    }

    protected function formatAuditionParticipant(AuditionParticipant $p): array
    {
        return [
            'id' => $p->id,
            'registration_number' => $p->registration_number,
            'bib_number' => $p->bib_number,
            'full_name' => $p->full_name,
            'nik' => $p->nik,
            'birth_date' => $p->birth_date?->format('d/m/Y'),
            'gender' => $p->gender,
            'preferred_position' => $p->preferred_position,
            'secondary_position' => $p->secondary_position,
            'dominant_foot' => $p->dominant_foot,
            'height_cm' => $p->height_cm,
            'weight_kg' => $p->weight_kg,
            'current_club_ssb' => $p->current_club_ssb,
            'jersey_size' => $p->jersey_size,
            'emergency_contact_name' => $p->emergency_contact_name,
            'emergency_contact_phone' => $p->emergency_contact_phone,
            'screening_status' => $p->screening_status,
            'screening_status_label' => $p->screeningStatusLabel(),
            'stage_status_label' => $p->stageStatusLabel(),
            'status' => $p->status,
            'is_paid' => $p->isPaid(),
            'can_download_bib' => $p->canDownloadBib(),
            'photo_url' => $p->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
            'slot_expires_at' => $p->slot_expires_at?->toIso8601String(),
            'event' => $p->event ? [
                'id' => $p->event->id,
                'name' => $p->event->name,
                'slug' => $p->event->slug,
                'age_category' => $p->event->age_category,
                'city' => $p->event->city,
                'venue_name' => $p->event->venue_name,
                'match_start_date' => $p->event->match_start_date?->format('d/m/Y'),
            ] : null,
            'session' => $p->eventSession ? [
                'id' => $p->eventSession->id,
                'session_name' => $p->eventSession->session_name,
                'start_time' => $p->eventSession->start_time,
                'end_time' => $p->eventSession->end_time,
            ] : null,
        ];
    }
}

