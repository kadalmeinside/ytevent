<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditionParticipant;
use App\Models\Player;
use Inertia\Inertia;
use Inertia\Response;

class CertificateVerificationController extends Controller
{
    /**
     * Memverifikasi keabsahan E-Sertifikat resmi melalui QR code di sertifikat.
     */
    public function verify(string $code): Response
    {
        $code = trim($code);

        // Kasus 1: Piagam Audisi & Seleksi Bakat (CERT-AUD-YYYY-EEE-PPPP)
        if (str_starts_with($code, 'CERT-AUD-')) {
            $parts = explode('-', $code);
            $participantId = isset($parts[4]) ? (int) $parts[4] : 0;

            $participant = AuditionParticipant::query()
                ->where('id', $participantId)
                ->with(['event'])
                ->first();

            if (! $participant || ! $participant->isPaid() || ! $participant->isScreeningPassed()) {
                return Inertia::render('Public/CertificateVerification', [
                    'isVerified' => false,
                    'code' => $code,
                    'certificate' => null,
                ]);
            }

            $predicate = match ($participant->stage_status) {
                'final_selected' => 'ATLET TERPILIH - SKUAD UTAMA / AKADEMI',
                'day_2_passed' => 'FINALIS SELEKSI BAKAT (TAHAP AKHIR)',
                'day_1_passed' => 'LOLOS SELEKSI TAHAP 1 (HARI 2)',
                default => 'PARTISIPASI & PENGHARGAAN RESMI',
            };

            return Inertia::render('Public/CertificateVerification', [
                'isVerified' => true,
                'code' => $code,
                'certificate' => [
                    'recipient_name' => $participant->full_name,
                    'bib_number' => $participant->bib_number,
                    'position' => $participant->preferred_position,
                    'event_name' => $participant->event?->name,
                    'predicate' => $predicate,
                    'club' => $participant->current_club_ssb ?: 'Independen',
                    'type' => 'Audisi & Seleksi Bakat',
                ],
            ]);
        }

        // Kasus 2: Piagam Kompetisi Turnamen Tim (CERT-YT-...)
        if (str_starts_with($code, 'CERT-YT-')) {
            $parts = explode('-', $code);
            $playerId = isset($parts[3]) ? (int) $parts[3] : 0;

            $player = Player::query()
                ->where('id', $playerId)
                ->with(['team.event'])
                ->first();

            if (! $player || $player->screening_status !== 'passed') {
                return Inertia::render('Public/CertificateVerification', [
                    'isVerified' => false,
                    'code' => $code,
                    'certificate' => null,
                ]);
            }

            return Inertia::render('Public/CertificateVerification', [
                'isVerified' => true,
                'code' => $code,
                'certificate' => [
                    'recipient_name' => $player->full_name,
                    'jersey_number' => $player->jersey_number,
                    'position' => $player->position,
                    'event_name' => $player->team?->event?->name,
                    'predicate' => $player->team?->achievement_title ?: 'PESERTA TERAKREDITASI RESMI',
                    'club' => $player->team?->name,
                    'type' => 'Kompetisi Turnamen Sepak Bola',
                ],
            ]);
        }

        return Inertia::render('Public/CertificateVerification', [
            'isVerified' => false,
            'code' => $code,
            'certificate' => null,
        ]);
    }
}

