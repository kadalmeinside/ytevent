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
     * Compute HMAC-SHA256 cryptographic signature for Digital Pass authenticity verification.
     */
    public static function computeAuthSignature(AuditionParticipant $participant): string
    {
        $payload = implode('|', [
            (string) $participant->event_id,
            (string) $participant->id,
            (string) ($participant->registration_number ?: ''),
            (string) ($participant->screening_code ?: ''),
        ]);

        $raw = strtoupper(substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 16));

        return implode('-', str_split($raw, 4));
    }

    /**
     * Generate A4 Portrait Official Digital Pass (E-Tiket) PDF with Anti-Joki QR Code,
     * Event Banner Overlay Header, and Cryptographic Authenticity Metadata.
     */
    public function generateBibPdf(AuditionParticipant $participant): \Barryvdh\DomPDF\PDF
    {
        if (! $participant->canDownloadBib()) {
            throw new HttpException(403, 'Digital Pass (E-Tiket) belum dapat diunduh. Pastikan pembayaran telah lunas dan berkas screening telah disetujui panitia.');
        }

        $participant->loadMissing(['event', 'eventSession', 'user', 'screeningOfficer', 'latestPayment']);
        $event = $participant->event;

        $verifyUrl = route('public.audition.verify', ['code' => $participant->screening_code ?: 'UNVERIFIED']);
        $qrSvg = (string) QrCode::format('svg')
            ->size(150)
            ->errorCorrection('H')
            ->generate($verifyUrl);
        $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        $photoBase64 = $this->toDompdfDataUri($this->readStorageImage($participant->photo_path));
        $bannerOverlayBase64 = $this->createOverlayBannerDataUri($this->readStorageImage($event?->banner_path));

        $validDatesLabel = null;
        if ($event?->match_start_date && $event?->match_end_date) {
            $validDatesLabel = $event->match_start_date->isSameDay($event->match_end_date)
                ? $event->match_start_date->translatedFormat('d M Y')
                : $event->match_start_date->translatedFormat('d M') . ' - ' . $event->match_end_date->translatedFormat('d M Y');
        } elseif ($event?->match_start_date) {
            $validDatesLabel = $event->match_start_date->translatedFormat('d M Y');
        }

        $data = [
            'participant' => $participant,
            'event' => $event,
            'session' => $participant->eventSession,
            'qr_code_svg' => $qrBase64,
            'photo_base64' => $photoBase64,
            'banner_overlay_base64' => $bannerOverlayBase64,
            'auth_signature' => self::computeAuthSignature($participant),
            'invoice_number' => $participant->latestPayment?->invoice_number ?: '-',
            'paid_at_label' => $participant->latestPayment?->paid_at?->translatedFormat('d M Y H:i') . ($participant->latestPayment?->paid_at ? ' WIB' : ''),
            'verified_by_name' => $participant->screeningOfficer?->name ?: 'Panitia Screening Resmi',
            'verified_at_label' => $participant->screening_verified_at
                ? $participant->screening_verified_at->translatedFormat('d M Y H:i') . ' WIB'
                : 'Terverifikasi Sistem',
            'valid_dates_label' => $validDatesLabel ?: 'Mengikuti Jadwal Resmi',
            'verify_url' => $verifyUrl,
            'generated_at' => now()->translatedFormat('d F Y, H:i') . ' WIB',
        ];

        return Pdf::loadView('pdf.audition_bib_tag', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);
    }

    /**
     * Read image bytes and MIME type from public or private storage disk.
     */
    private function readStorageImage(?string $path): ?array
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public')->exists($path)
            ? Storage::disk('public')
            : (Storage::disk('private')->exists($path) ? Storage::disk('private') : null);

        if (! $disk) {
            return null;
        }

        $bytes = $disk->get($path);
        if ($bytes === '' || $bytes === false) {
            return null;
        }

        return [
            'bytes' => $bytes,
            'mime' => $disk->mimeType($path) ?: 'image/jpeg',
        ];
    }

    /**
     * Convert image bytes to a DomPDF-safe data URI (transcoding WebP to JPEG via GD).
     */
    private function toDompdfDataUri(?array $file): ?string
    {
        if (! $file) {
            return null;
        }

        $mime = strtolower($file['mime']);
        if (str_contains($mime, 'webp') && extension_loaded('gd')) {
            $img = @imagecreatefromstring($file['bytes']);
            if ($img !== false) {
                ob_start();
                imagejpeg($img, null, 88);
                $jpegBytes = ob_get_clean();
                imagedestroy($img);
                if ($jpegBytes !== false && $jpegBytes !== '') {
                    return 'data:image/jpeg;base64,' . base64_encode($jpegBytes);
                }
            }
        }

        return 'data:' . $file['mime'] . ';base64,' . base64_encode($file['bytes']);
    }

    /**
     * Render a dark-scrimmed banner data URI for the Digital Pass header while preserving aspect ratio.
     */
    private function createOverlayBannerDataUri(?array $file): ?string
    {
        if (! $file || ! extension_loaded('gd')) {
            return null;
        }

        $src = @imagecreatefromstring($file['bytes']);
        if ($src === false) {
            return null;
        }

        $srcW = max(1, imagesx($src));
        $srcH = max(1, imagesy($src));
        $dstW = min(1200, $srcW);
        $dstH = max(1, (int) round($srcH * ($dstW / $srcW)));

        $dst = imagecreatetruecolor($dstW, $dstH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);

        // Subtle dark slate scrim (GD alpha: 0=opaque, 127=transparent) so banner stays clear while white text stays legible
        for ($x = 0; $x < $dstW; $x += 4) {
            // ~58% slate on the left -> ~35% slate on the right
            $alpha = (int) round(53 + (29 * ($x / $dstW)));
            $scrim = imagecolorallocatealpha($dst, 15, 23, 42, $alpha);
            imagefilledrectangle($dst, $x, 0, min($dstW - 1, $x + 3), $dstH - 1, $scrim);
        }

        ob_start();
        imagejpeg($dst, null, 90);
        $out = ob_get_clean();
        imagedestroy($dst);

        return ($out !== false && $out !== '')
            ? 'data:image/jpeg;base64,' . base64_encode($out)
            : null;
    }
}

