<?php

namespace App\Services;

use App\Models\AuditionParticipant;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AuditionCertificateService
{
    /**
     * Menghasilkan PDF E-Sertifikat Resmi Audisi & Seleksi Bakat Persija Development.
     */
    public function generateAuditionCertificatePdf(AuditionParticipant $participant): \Barryvdh\DomPDF\PDF
    {
        $participant->loadMissing(['event', 'eventSession', 'user']);
        $event = $participant->event;

        $predicateTitle = match ($participant->stage_status) {
            'final_selected' => 'ATLET TERPILIH - SKUAD UTAMA / AKADEMI',
            'day_2_passed' => 'FINALIS SELEKSI BAKAT (TAHAP AKHIR)',
            'day_1_passed' => 'LOLOS SELEKSI TAHAP 1 (HARI 2)',
            default => 'PARTISIPASI & PENGHARGAAN RESMI',
        };

        $year = now()->format('Y');
        $certNumber = sprintf('CERT-AUD-%s-%03d-%04d', $year, $event?->id ?? 1, $participant->id);

        $verifyUrl = route('public.certificate.verify', ['code' => $certNumber]);
        $qrSvg = (string) QrCode::format('svg')
            ->size(100)
            ->errorCorrection('H')
            ->generate($verifyUrl);
        $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        $data = [
            'participant' => $participant,
            'event' => $event,
            'certificate_number' => $certNumber,
            'predicate_title' => $predicateTitle,
            'qr_code_svg' => $qrBase64,
            'issued_at' => now()->translatedFormat('d F Y'),
        ];

        return Pdf::loadView('pdf.audition_certificate', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);
    }
}
