<?php

namespace App\Services;

use App\Models\AuditionParticipant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuditionBibPdfService
{
    /**
     * Generate A4 Landscape Official Bib Tag PDF with Anti-Joki QR Code and Athlete Photo.
     */
    public function generateBibPdf(AuditionParticipant $participant): \Barryvdh\DomPDF\PDF
    {
        if (! $participant->canDownloadBib()) {
            throw new HttpException(403, 'Digital Pass (E-Tiket) belum dapat diunduh. Pastikan pembayaran telah lunas dan berkas screening telah disetujui panitia.');
        }

        $participant->loadMissing(['event', 'eventSession', 'user']);

        $verifyUrl = route('public.audition.verify', ['code' => $participant->screening_code ?: 'UNVERIFIED']);
        $qrSvg = (string) QrCode::format('svg')
            ->size(150)
            ->errorCorrection('H')
            ->generate($verifyUrl);
        $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        $photoBase64 = null;
        if ($participant->photo_path) {
            $disk = Storage::disk('public')->exists($participant->photo_path)
                ? Storage::disk('public')
                : (Storage::disk('private')->exists($participant->photo_path) ? Storage::disk('private') : null);

            if ($disk) {
                $mime = $disk->mimeType($participant->photo_path) ?: 'image/jpeg';
                $photoBase64 = 'data:' . $mime . ';base64,' . base64_encode($disk->get($participant->photo_path));
            }
        }

        $data = [
            'participant' => $participant,
            'event' => $participant->event,
            'session' => $participant->eventSession,
            'qr_code_svg' => $qrBase64,
            'photo_base64' => $photoBase64,
            'generated_at' => now()->translatedFormat('d F Y, H:i') . ' WIB',
        ];

        return Pdf::loadView('pdf.audition_bib_tag', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);
    }
}

