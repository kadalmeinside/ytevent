<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarkPaymentRefundedRequest;
use App\Http\Requests\Admin\VerifyManualPaymentRequest;
use App\Models\Event;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentVerificationController extends Controller
{
    public function manualQueue(): Response
    {
        $this->authorize('viewAny', Payment::class);

        $queue = Payment::query()
            ->with(['team', 'event', 'verifier'])
            ->where('status', 'waiting_verification')
            ->orderBy('updated_at')
            ->get()
            ->map(fn (Payment $p) => $this->transformPayment($p));

        $totalManualEver = Payment::query()
            ->where(function ($q) {
                $q->whereNotNull('proof_file_path')
                    ->orWhere('method', 'manual');
            })
            ->count();

        $totalVerified = Payment::query()
            ->whereIn('status', ['paid', 'rejected'])
            ->where(function ($q) {
                $q->whereNotNull('proof_file_path')
                    ->orWhere('method', 'manual');
            })
            ->count();

        return Inertia::render('Admin/Payments/ManualVerification', [
            'queue' => $queue,
            'totalManualEver' => $totalManualEver,
            'totalVerified' => $totalVerified,
        ]);
    }

    public function transactions(Request $request): Response
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()
            ->with(['team', 'event', 'verifier'])
            ->orderByDesc('created_at');

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($method = $request->query('method')) {
            $query->where('method', $method);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('team', fn ($tq) => $tq->where('name', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->get()->map(fn (Payment $p) => $this->transformPayment($p));

        return Inertia::render('Admin/Payments/Transactions', [
            'transactions' => $transactions,
            'eventsFilter' => Event::query()->select(['id', 'name', 'age_category'])->orderBy('name')->get(),
            'filters' => [
                'event_id' => (string) $request->query('event_id', ''),
                'status' => (string) $request->query('status', ''),
                'method' => (string) $request->query('method', ''),
                'search' => (string) $request->query('search', ''),
            ],
        ]);
    }

    public function verifyManual(
        VerifyManualPaymentRequest $request,
        Payment $payment,
        PaymentService $paymentService
    ): RedirectResponse {
        $this->authorize('verifyManual', $payment);
        $validated = $request->validated();

        if ($validated['action'] === 'approve') {
            $approved = $paymentService->approveManualPayment($payment, $request->user());

            if ($approved->status === 'refund_required') {
                return redirect()->back()
                    ->with('warning', "Pembayaran {$payment->invoice_number} diterima setelah kuota penuh, sehingga dialihkan ke status Perlu Refund.");
            }

            return redirect()->back()
                ->with('success', "Pembayaran {$payment->invoice_number} disetujui (LUNAS). Status tim kini AKTIF.");
        }

        $paymentService->rejectManualPayment(
            payment: $payment,
            verifier: $request->user(),
            reason: $validated['rejection_reason']
        );

        return redirect()->back()
            ->with('warning', "Bukti pembayaran {$payment->invoice_number} ditolak dan diberi masa tenggang unggah ulang.");
    }

    /**
     * Menandai transaksi berstatus refund_required menjadi refunded.
     */
    public function markRefunded(
        MarkPaymentRefundedRequest $request,
        Payment $payment,
        PaymentService $paymentService
    ): RedirectResponse {
        $this->authorize('verifyManual', $payment);

        $validated = $request->validated();

        $paymentService->markRefunded(
            payment: $payment,
            verifier: $request->user(),
            refundNotes: $validated['refund_notes'],
            refundAmount: isset($validated['refund_amount']) ? (float) $validated['refund_amount'] : null,
            refundReference: $validated['refund_reference'] ?? null,
            refundMethod: $validated['refund_method'] ?? null,
        );

        return redirect()->back()
            ->with('success', "Transaksi {$payment->invoice_number} berhasil ditandai REFUND SELESAI dan notifikasi email telah dikirim ke manajer tim.");
    }

    /**
     * Ekspor laporan transaksi pembayaran ke format CSV (UTF-8 BOM kompatibel Excel).
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()
            ->with(['team', 'event', 'verifier'])
            ->orderByDesc('created_at');

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($method = $request->query('method')) {
            $query->where('method', $method);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('team', fn ($tq) => $tq->where('name', 'like', "%{$search}%"));
            });
        }

        $payments = $query->get();
        $filename = 'laporan-transaksi-ytevent-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'No. Invoice',
                'Tanggal Dibuat',
                'Tanggal Lunas',
                'Kompetisi',
                'Kategori Usia',
                'Nama Tim',
                'Manajer Tim',
                'WhatsApp Manajer',
                'Metode',
                'Kanal Pembayaran',
                'Nominal Tagihan (Rp)',
                'Biaya Layanan (Rp)',
                'Total Bayar (Rp)',
                'Status Pembayaran',
                'Verifikator',
                'Catatan / Alasan',
                'Nominal Refund (Rp)',
                'Metode Refund',
                'Referensi Refund',
                'Waktu Refund',
                'Catatan Refund',
            ]);

            foreach ($payments as $p) {
                fputcsv($out, [
                    $p->invoice_number,
                    $p->created_at?->format('Y-m-d H:i:s') ?? '-',
                    $p->paid_at?->format('Y-m-d H:i:s') ?? '-',
                    $p->event?->name ?? '-',
                    $p->event?->age_category ?? '-',
                    $p->team?->name ?? '-',
                    $p->team?->manager_name ?? '-',
                    $p->team?->manager_whatsapp ?? '-',
                    $p->method ?? 'manual',
                    $p->payment_channel ?? '-',
                    (float) $p->amount,
                    (float) ($p->gateway_fee ?? 0),
                    (float) $p->total_amount,
                    $p->status,
                    $p->verifier?->name ?? '-',
                    $p->rejection_reason ?? '-',
                    $p->refund_amount !== null ? (float) $p->refund_amount : '-',
                    $p->refund_method ?? '-',
                    $p->refund_reference ?? '-',
                    $p->refunded_at?->format('Y-m-d H:i:s') ?? '-',
                    $p->refund_notes ?? '-',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function transformPayment(Payment $p): array
    {
        $ext = $p->proof_file_path
            ? strtolower(pathinfo($p->proof_file_path, PATHINFO_EXTENSION))
            : null;

        return [
            'id' => $p->id,
            'invoice_number' => $p->invoice_number,
            'amount' => (float) $p->amount,
            'total_amount' => (float) $p->total_amount,
            'method' => $p->method,
            'payment_channel' => $p->payment_channel,
            'external_reference_id' => $p->external_reference_id,
            'sender_bank_name' => $p->sender_bank_name,
            'sender_account_holder' => $p->sender_account_holder,
            'sender_account_number' => $p->sender_account_number,
            'transfer_date' => $p->transfer_date?->toDateString(),
            'status' => $p->status,
            'rejection_reason' => $p->rejection_reason,
            'refund_amount' => $p->refund_amount !== null ? (float) $p->refund_amount : null,
            'refund_reference' => $p->refund_reference,
            'refund_method' => $p->refund_method,
            'refund_notes' => $p->refund_notes,
            'refunded_at' => $p->refunded_at?->toIso8601String(),
            'expires_at' => $p->expires_at?->toIso8601String(),
            'paid_at' => $p->paid_at?->toIso8601String(),
            'verified_at' => $p->verified_at?->toIso8601String(),
            'verifier_name' => $p->verifier?->name,
            'proof_url' => $p->proof_file_path
                ? route('private.payment-proof', ['payment' => $p->id])
                : null,
            'proof_is_pdf' => $ext === 'pdf',
            'team' => $p->team ? [
                'id' => $p->team->id,
                'name' => $p->team->name,
                'origin_association' => $p->team->origin_association,
                'manager_name' => $p->team->manager_name,
                'manager_whatsapp' => $p->team->manager_whatsapp,
                'logo_url' => $p->team->logo_path ? Storage::disk('public')->url($p->team->logo_path) : null,
            ] : null,
            'event' => $p->event ? [
                'id' => $p->event->id,
                'name' => $p->event->name,
                'age_category' => $p->event->age_category,
            ] : null,
        ];
    }
}

