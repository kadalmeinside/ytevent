<?php

namespace App\Services;

use App\Models\Player;
use App\Models\TournamentMatch;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateService
{
    /**
     * Menghasilkan PDF E-Certificate / Piagam Penghargaan Resmi untuk Pemain Terakreditasi.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generatePlayerCertificatePdf(Player $player, ?string $customPredicate = null)
    {
        $player->loadMissing(['team.event']);
        $team = $player->team;
        $event = $player->event ?? $team?->event;

        $predicate = $customPredicate
            ?: ($team?->achievement_title ?: 'PESERTA TERAKREDITASI RESMI');

        $verifyUrl = route('public.player.verify', ['code' => $player->accreditation_code ?: 'UNVERIFIED']);
        $qrSvg = (string) QrCode::format('svg')
            ->size(100)
            ->errorCorrection('H')
            ->generate($verifyUrl);

        $playerPhotoBase64 = null;
        if ($player->photo_path && Storage::disk('private')->exists($player->photo_path)) {
            $mime = Storage::disk('private')->mimeType($player->photo_path) ?: 'image/jpeg';
            $playerPhotoBase64 = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('private')->get($player->photo_path));
        }

        $data = [
            'player' => $player,
            'team' => $team,
            'event' => $event,
            'predicate' => strtoupper($predicate),
            'photo_base64' => $playerPhotoBase64,
            'qr_code_svg' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            'issued_at' => now()->translatedFormat('d F Y'),
            'certificate_number' => sprintf('CERT-YT-%02d-%04d-%s', $event?->id ?? 1, $player->id, date('Y')),
        ];

        return Pdf::loadView('pdf.player_certificate', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);
    }

    /**
     * Menghasilkan PDF Daftar Susunan Pemain (Digital Match Lineup / DSP) untuk Pengawas Pertandingan.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateMatchLineupPdf(TournamentMatch $match, int $teamId)
    {
        $match->loadMissing(['event', 'group', 'homeTeam', 'awayTeam', 'lineups.player']);
        $team = (int) $match->home_team_id === $teamId ? $match->homeTeam : $match->awayTeam;
        $opponent = (int) $match->home_team_id === $teamId ? $match->awayTeam : $match->homeTeam;

        $lineups = $match->lineups
            ->where('team_id', $teamId)
            ->sortBy(fn ($l) => ($l->is_starting ? 0 : 100) + (int) ($l->jersey_number_snapshot ?? $l->player?->jersey_number ?? 99))
            ->values();

        $startingPlayers = $lineups->where('is_starting', true)->values();
        $substitutePlayers = $lineups->where('is_starting', false)->values();

        $data = [
            'match' => $match,
            'event' => $match->event,
            'team' => $team,
            'opponent' => $opponent,
            'starting_players' => $startingPlayers,
            'substitute_players' => $substitutePlayers,
            'generated_at' => now()->translatedFormat('d F Y, H:i') . ' WIB',
        ];

        return Pdf::loadView('pdf.match_lineup_sheet', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);
    }
}

