<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InvoicePdfService
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function generateInvoicePdf(Payment $payment): \Barryvdh\DomPDF\PDF
    {
        $invoice = $this->invoiceService->resolveInvoiceData($payment);

        $qrPayload = implode("\n", [
            $invoice['document_title'],
            'No: ' . $invoice['invoice_number'],
            'Peserta: ' . ($invoice['payer']['name'] ?? '-'),
            'Total: Rp ' . number_format((float) $invoice['total_amount'], 0, ',', '.'),
            'Status: ' . $invoice['status_label'],
            'Hash: ' . $invoice['verification_hash'],
        ]);

        $qrSvg = (string) QrCode::format('svg')
            ->size(110)
            ->errorCorrection('M')
            ->generate($qrPayload);
        $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        $logoBase64 = $this->resolveAppLogoBase64();

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'payment' => $payment,
            'qrBase64' => $qrBase64,
            'logoBase64' => $logoBase64,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ]);
    }

    public function filename(Payment $payment): string
    {
        $safeNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $payment->invoice_number);
        $prefix = $payment->status === 'paid' ? 'Kwitansi' : ($payment->status === 'refunded' ? 'Nota-Refund' : 'Invoice');

        return "{$prefix}-{$safeNumber}.pdf";
    }

    protected function resolveAppLogoBase64(): ?string
    {
        $logoPath = SystemSetting::get('app_logo');
        if (! $logoPath || ! is_string($logoPath)) {
            return null;
        }

        if (! Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        $bytes = Storage::disk('public')->get($logoPath);
        if (! $bytes) {
            return null;
        }

        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => null,
        };

        if (! $mime) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}

