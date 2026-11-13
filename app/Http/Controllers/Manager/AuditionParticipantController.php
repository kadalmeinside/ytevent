<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\AuditionParticipant;
use App\Services\AuditionBibPdfService;
use App\Services\AuditionCertificateService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditionParticipantController extends Controller
{
    /**
     * Download Nomor Dada (Bib Tag) PDF A4 Landscape.
     */
    public function downloadBibTag(Request $request, AuditionParticipant $participant, AuditionBibPdfService $pdfService): Response
    {
        $user = $request->user();

        // Autorisasi: pemilik akun (orang tua) atau panitia dengan hak akses
        $isOwner = $user && $user->id === $participant->user_id;
        $isStaff = $user && ($user->hasRole('superadmin') || $user->can('screening.view_queue') || $user->can('teams.view_all'));

        abort_unless($isOwner || $isStaff, 403, 'Anda tidak memiliki hak akses untuk mengunduh Digital Pass peserta ini.');

        if (! $participant->canDownloadBib()) {
            abort(403, 'Digital Pass (E-Tiket) belum dapat diunduh. Pastikan pembayaran telah lunas dan berkas screening telah disetujui panitia.');
        }

        $pdf = $pdfService->generateBibPdf($participant);
        $cleanName = preg_replace('/[^A-Za-z0-9_-]/', '_', $participant->full_name);
        $ticketId = $participant->registration_number ?: ('PASS-' . $participant->id);
        $fileName = sprintf('DIGITAL-PASS-%s-%s.pdf', $ticketId, $cleanName);

        return $pdf->download($fileName);
    }

    /**
     * Download E-Certificate Resmi Audisi & Seleksi Bakat.
     */
    public function downloadCertificate(Request $request, AuditionParticipant $participant, AuditionCertificateService $certService): Response
    {
        $user = $request->user();

        $isOwner = $user && $user->id === $participant->user_id;
        $isStaff = $user && ($user->hasRole('superadmin') || $user->can('screening.view_queue') || $user->can('teams.view_all'));

        abort_unless($isOwner || $isStaff, 403, 'Anda tidak memiliki hak akses untuk mengunduh piagam peserta ini.');
        abort_unless($participant->canDownloadCertificate(), 403, 'Sertifikat belum memenuhi syarat untuk diterbitkan.');

        $pdf = $certService->generateAuditionCertificatePdf($participant);
        $cleanName = preg_replace('/[^A-Za-z0-9_-]/', '_', $participant->full_name);
        $fileName = sprintf('SERTIFIKAT-AUDISI-%s-%s.pdf', $participant->registration_number, $cleanName);

        return $pdf->download($fileName);
    }
}
