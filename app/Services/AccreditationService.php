<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AccreditationService
{
    /**
     * Mengecek kelayakan skuad tim sebelum melakukan penguncian final.
     *
     * @return array<string, mixed>
     */
    public function checkReadiness(Team $team): array
    {
        $team->loadMissing(['event', 'players', 'officials']);
        $event = $team->event;

        $minPlayers = (int) ($event?->min_players_per_team ?? 14);
        $maxPlayers = (int) ($event?->max_players_per_team ?? 18);
        $minGk = (int) ($event?->min_gk_per_team ?? 2);
        $showCoachLicense = (bool) ($event?->show_coach_license ?? true);
        $requireCoachLicense = $showCoachLicense && (bool) ($event?->require_coach_license ?? false);

        $passedPlayers = $team->players->where('screening_status', 'passed');
        $passedPlayersCount = $passedPlayers->count();
        $gkPassedCount = $passedPlayers->where('position', 'GK')->count();

        $submittedCount = $team->players->where('screening_status', 'submitted')->count();
        $rejectedCount = $team->players->where('screening_status', 'rejected')->count();
        $officialsCount = $team->officials->count();
        $headCoach = $team->officials->firstWhere('role', 'Head Coach');
        $hasCoachLicense = ! empty(trim((string) ($team->head_coach_license ?? '')))
            || ! empty(trim((string) ($headCoach?->license_number ?? '')));
        $isCoachLicenseMet = ! $requireCoachLicense || $hasCoachLicense;

        $isPaid = in_array($team->status, ['active', 'locked_roster'], true);
        $isLocked = $team->isRosterLocked();

        $blockingReasons = [];

        if (! $isPaid) {
            $blockingReasons[] = 'Status tim belum aktif atau pembayaran belum diverifikasi lunas.';
        }

        if ($submittedCount > 0) {
            $blockingReasons[] = "Masih ada {$submittedCount} pemain yang dalam antrean pemeriksaan panitia.";
        }

        if ($passedPlayersCount < $minPlayers) {
            $blockingReasons[] = "Jumlah pemain yang sah ({$passedPlayersCount}) belum memenuhi kuota minimal ({$minPlayers} pemain).";
        }

        if ($gkPassedCount < $minGk) {
            $blockingReasons[] = "Wajib memiliki minimal {$minGk} Penjaga Gawang (GK) yang lolos verifikasi (saat ini: {$gkPassedCount} GK sah).";
        }

        if ($officialsCount < 1) {
            $blockingReasons[] = 'Minimal 1 pelatih kepala / ofisial tim wajib terdaftar.';
        }

        if (! $isCoachLicenseMet) {
            $blockingReasons[] = 'Nomor lisensi Pelatih Kepala (Head Coach) wajib dilengkapi sesuai regulasi kompetisi ini.';
        }

        $canLock = empty($blockingReasons) && ! $isLocked;

        return [
            'is_ready' => $canLock,
            'is_locked' => $isLocked,
            'roster_locked_at' => $team->roster_locked_at ? $team->roster_locked_at->translatedFormat('d F Y, H:i') . ' WIB' : null,
            'passed_players_count' => $passedPlayersCount,
            'min_players_required' => $minPlayers,
            'max_players_allowed' => $maxPlayers,
            'is_min_players_met' => $passedPlayersCount >= $minPlayers,
            'gk_passed_count' => $gkPassedCount,
            'min_gk_required' => $minGk,
            'is_gk_met' => $gkPassedCount >= $minGk,
            'officials_count' => $officialsCount,
            'is_officials_met' => $officialsCount >= 1 && $isCoachLicenseMet,
            'show_coach_license' => $showCoachLicense,
            'require_coach_license' => $requireCoachLicense,
            'is_coach_license_met' => $isCoachLicenseMet,
            'submitted_players_count' => $submittedCount,
            'rejected_players_count' => $rejectedCount,
            'has_in_review_players' => $submittedCount > 0,
            'is_paid' => $isPaid,
            'blocking_reasons' => $blockingReasons,
        ];
    }

    /**
     * Mengunci skuad final dan menerbitkan akreditasi resmi.
     *
     * @throws ValidationException
     */
    public function lockRoster(Team $team, User $actor): void
    {
        $readiness = $this->checkReadiness($team);

        if ($readiness['is_locked']) {
            throw ValidationException::withMessages([
                'roster' => 'Skuad tim ini telah dikunci sebelumnya.',
            ]);
        }

        if (! $readiness['is_ready']) {
            throw ValidationException::withMessages([
                'roster' => $readiness['blocking_reasons'][0] ?? 'Skuad belum memenuhi syarat pengesahan final.',
            ]);
        }

        DB::transaction(function () use ($team) {
            $team->update([
                'status' => 'locked_roster',
                'roster_locked_at' => now(),
                'screening_status' => 'all_passed',
            ]);
        });

        app(EmailNotificationService::class)->sendRosterLocked($team->fresh());
    }

    /**
     * Mempersiapkan data PDF album roster tim (Team Roster Sheet).
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateRosterSheetPdf(Team $team)
    {
        $team->loadMissing(['event', 'officials']);
        $players = $team->players()
            ->where('screening_status', 'passed')
            ->orderBy('jersey_number')
            ->get();

        $preparedPlayers = $players->map(function (Player $player) {
            $qrSvg = (string) QrCode::format('svg')
                ->size(90)
                ->errorCorrection('H')
                ->generate(route('public.player.verify', ['code' => $player->accreditation_code]));

            $playerPhotoBase64 = null;
            if ($player->photo_path && Storage::disk('private')->exists($player->photo_path)) {
                $mime = Storage::disk('private')->mimeType($player->photo_path) ?: 'image/jpeg';
                $playerPhotoBase64 = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('private')->get($player->photo_path));
            }

            return [
                'id' => $player->id,
                'full_name' => $player->full_name,
                'jersey_name' => $player->jersey_name ?: $player->full_name,
                'jersey_number' => $player->jersey_number,
                'position' => $player->position,
                'birth_date' => $player->birth_date?->format('d/m/Y'),
                'birth_place' => $player->birth_place,
                'nik' => $player->nik,
                'nisn' => $player->nisn ?: '-',
                'accreditation_code' => $player->accreditation_code,
                'photo_base64' => $playerPhotoBase64,
                'qr_code_svg' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            ];
        });

        $preparedOfficials = $team->officials->map(function ($official) {
            $officialPhotoBase64 = null;
            if ($official->photo_path && Storage::disk('private')->exists($official->photo_path)) {
                $mime = Storage::disk('private')->mimeType($official->photo_path) ?: 'image/jpeg';
                $officialPhotoBase64 = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('private')->get($official->photo_path));
            }

            return [
                'full_name' => $official->full_name,
                'role' => $official->role,
                'license_number' => $official->license_number ?: '-',
                'photo_base64' => $officialPhotoBase64,
            ];
        });

        $teamLogoBase64 = null;
        if ($team->logo_path && Storage::disk('public')->exists($team->logo_path)) {
            $mime = Storage::disk('public')->mimeType($team->logo_path) ?: 'image/png';
            $teamLogoBase64 = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($team->logo_path));
        }

        $data = [
            'team' => $team,
            'event' => $team->event,
            'players' => $preparedPlayers,
            'officials' => $preparedOfficials,
            'team_logo_base64' => $teamLogoBase64,
            'generated_at' => now()->translatedFormat('d F Y, H:i') . ' WIB',
            'locked_at' => $team->roster_locked_at ? $team->roster_locked_at->translatedFormat('d F Y, H:i') . ' WIB' : now()->translatedFormat('d F Y, H:i') . ' WIB',
        ];

        return Pdf::loadView('pdf.team_roster_sheet', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);
    }

    /**
     * Mempersiapkan data PDF kartu akreditasi (ID Cards Lanyard).
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateIdCardsPdf(Team $team)
    {
        $team->loadMissing(['event', 'officials']);
        $players = $team->players()
            ->where('screening_status', 'passed')
            ->orderBy('jersey_number')
            ->get();

        $teamLogoBase64 = null;
        if ($team->logo_path && Storage::disk('public')->exists($team->logo_path)) {
            $mime = Storage::disk('public')->mimeType($team->logo_path) ?: 'image/png';
            $teamLogoBase64 = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($team->logo_path));
        }

        $cards = [];

        foreach ($players as $player) {
            $qrSvg = (string) QrCode::format('svg')
                ->size(80)
                ->errorCorrection('H')
                ->generate(route('public.player.verify', ['code' => $player->accreditation_code]));

            $playerPhotoBase64 = null;
            if ($player->photo_path && Storage::disk('private')->exists($player->photo_path)) {
                $mime = Storage::disk('private')->mimeType($player->photo_path) ?: 'image/jpeg';
                $playerPhotoBase64 = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('private')->get($player->photo_path));
            }

            $cards[] = [
                'type' => 'player',
                'name' => $player->full_name,
                'jersey_name' => $player->jersey_name ?: $player->full_name,
                'jersey_number' => $player->jersey_number,
                'role' => $player->position,
                'role_label' => 'PEMAIN',
                'accreditation_code' => $player->accreditation_code,
                'photo_base64' => $playerPhotoBase64,
                'qr_code_svg' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            ];
        }

        foreach ($team->officials as $official) {
            $officialPhotoBase64 = null;
            if ($official->photo_path && Storage::disk('private')->exists($official->photo_path)) {
                $mime = Storage::disk('private')->mimeType($official->photo_path) ?: 'image/jpeg';
                $officialPhotoBase64 = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('private')->get($official->photo_path));
            }

            $officialCode = 'OFF-' . str_pad((string) $official->id, 4, '0', STR_PAD_LEFT);
            $officialQrTarget = route('public.official.verify', ['code' => $officialCode]);

            $officialQrSvg = (string) QrCode::format('svg')
                ->size(80)
                ->errorCorrection('H')
                ->generate($officialQrTarget);

            $cards[] = [
                'type' => 'official',
                'name' => $official->full_name,
                'jersey_name' => null,
                'jersey_number' => null,
                'role' => $official->role,
                'role_label' => 'OFISIAL / ' . strtoupper($official->role),
                'license_number' => $official->license_number ?: null,
                'accreditation_code' => $officialCode,
                'photo_base64' => $officialPhotoBase64,
                'qr_code_svg' => 'data:image/svg+xml;base64,' . base64_encode($officialQrSvg),
            ];
        }

        $data = [
            'team' => $team,
            'event' => $team->event,
            'cards' => $cards,
            'team_logo_base64' => $teamLogoBase64,
            'generated_at' => now()->translatedFormat('d F Y, H:i') . ' WIB',
        ];

        return Pdf::loadView('pdf.id_cards', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);
    }
}

