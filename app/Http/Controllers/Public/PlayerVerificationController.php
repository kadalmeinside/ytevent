<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\TeamOfficial;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlayerVerificationController extends Controller
{
    /**
     * Menampilkan profil verifikasi digital keabsahan pemain melalui QR code.
     */
    public function verify(string $code): Response
    {
        $player = Player::where('accreditation_code', $code)
            ->with(['team.event', 'verifiedBy'])
            ->first();

        if (! $player || $player->screening_status !== 'passed') {
            return Inertia::render('Public/PlayerVerification', [
                'isVerified' => false,
                'code' => $code,
                'player' => null,
            ]);
        }

        $team = $player->team;
        $event = $team->event;

        $maskedNik = $player->nik;
        if (strlen($maskedNik) >= 16) {
            $maskedNik = substr($maskedNik, 0, 6).'******'.substr($maskedNik, -4);
        }

        return Inertia::render('Public/PlayerVerification', [
            'isVerified' => true,
            'code' => $code,
            'player' => [
                'full_name' => $player->full_name,
                'jersey_name' => $player->jersey_name ?: $player->full_name,
                'jersey_number' => $player->jersey_number,
                'position' => $player->position,
                'dominant_foot' => $player->dominant_foot,
                'birth_place' => $player->birth_place,
                'birth_date' => $player->birth_date?->format('d/m/Y'),
                'age' => $player->birth_date ? (int) $player->birth_date->diffInYears(now()) : null,
                'masked_nik' => $maskedNik,
                'nisn' => $player->nisn ?: '-',
                'height_cm' => $player->height_cm,
                'weight_kg' => $player->weight_kg,
                'photo_url' => $player->photo_path
                    ? route('public.player.verify.photo', ['code' => $code])
                    : null,
                'accreditation_code' => $player->accreditation_code,
                'verified_at' => $player->verified_at ? $player->verified_at->translatedFormat('d F Y, H:i').' WIB' : null,
                'verified_by_name' => $player->verifiedBy?->name ?: 'Panitia Screening Resmi',
            ],
            'team' => [
                'name' => $team->name,
                'origin_association' => $team->origin_association,
                'logo_url' => $team->logo_optimized_path
                    ? asset('storage/'.$team->logo_optimized_path)
                    : ($team->logo_path ? asset('storage/'.$team->logo_path) : null),
                'manager_name' => $team->manager_name,
                'head_coach_name' => $team->head_coach_name,
                'is_roster_locked' => $team->isRosterLocked(),
            ],
            'event' => [
                'name' => $event->name,
                'age_category' => $event->age_category,
                'format' => $event->format,
                'venue_name' => $event->venue_name,
                'city' => $event->city,
                'match_start_date' => $event->match_start_date?->format('d/m/Y'),
                'match_end_date' => $event->match_end_date?->format('d/m/Y'),
            ],
        ]);
    }

    /**
     * Menyajikan foto pas resmi pemain terverifikasi untuk halaman publik QR.
     */
    public function photo(string $code): StreamedResponse
    {
        $player = Player::where('accreditation_code', $code)
            ->where('screening_status', 'passed')
            ->firstOrFail();

        $photoPath = $player->photo_optimized_path ?: $player->photo_path;
        if (! $photoPath || ! Storage::disk('private')->exists($photoPath)) {
            abort(404, 'Foto pemain tidak ditemukan.');
        }

        $mime = Storage::disk('private')->mimeType($photoPath) ?: 'image/jpeg';

        return response()->stream(function () use ($photoPath) {
            $stream = Storage::disk('private')->readStream($photoPath);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Menampilkan profil verifikasi digital keabsahan pelatih/ofisial melalui QR code.
     */
    public function verifyOfficial(string $code): Response
    {
        $official = $this->resolveOfficialByCode($code);

        if (! $official || ! $official->team || in_array($official->team->status, ['disqualified', 'expired'], true)) {
            return Inertia::render('Public/OfficialVerification', [
                'isVerified' => false,
                'code' => $code,
                'official' => null,
            ]);
        }

        $team = $official->team;
        $event = $team->event;

        return Inertia::render('Public/OfficialVerification', [
            'isVerified' => true,
            'code' => $official->accreditation_code,
            'official' => [
                'full_name' => $official->full_name,
                'role' => $official->role,
                'role_label' => TeamOfficial::ROLES[$official->role] ?? $official->role,
                'license_number' => $official->license_number ?: '-',
                'screening_status' => $official->screening_status,
                'photo_url' => $official->photo_path
                    ? route('public.official.verify.photo', ['code' => $official->accreditation_code])
                    : null,
                'accreditation_code' => $official->accreditation_code,
            ],
            'team' => [
                'name' => $team->name,
                'origin_association' => $team->origin_association,
                'logo_url' => $team->logo_optimized_path
                    ? asset('storage/'.$team->logo_optimized_path)
                    : ($team->logo_path ? asset('storage/'.$team->logo_path) : null),
                'manager_name' => $team->manager_name,
                'head_coach_name' => $team->head_coach_name,
                'is_roster_locked' => $team->isRosterLocked(),
            ],
            'event' => $event ? [
                'name' => $event->name,
                'age_category' => $event->age_category,
                'format' => $event->format,
                'venue_name' => $event->venue_name,
                'city' => $event->city,
                'match_start_date' => $event->match_start_date?->format('d/m/Y'),
                'match_end_date' => $event->match_end_date?->format('d/m/Y'),
            ] : null,
        ]);
    }

    /**
     * Menyajikan pas foto resmi pelatih/ofisial untuk halaman publik QR.
     */
    public function officialPhoto(string $code): StreamedResponse
    {
        $official = $this->resolveOfficialByCode($code);

        $photoPath = $official?->photo_optimized_path ?: $official?->photo_path;
        if (! $official || $official->screening_status !== 'passed' || ! $photoPath || ! Storage::disk('private')->exists($photoPath)) {
            abort(404, 'Foto ofisial tidak ditemukan.');
        }

        $mime = Storage::disk('private')->mimeType($photoPath) ?: 'image/jpeg';

        return response()->stream(function () use ($photoPath) {
            $stream = Storage::disk('private')->readStream($photoPath);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    protected function resolveOfficialByCode(string $code): ?TeamOfficial
    {
        $normalized = strtoupper(trim($code));
        if (! preg_match('/^OFF-[A-Z0-9]{24}$/', $normalized)) {
            return null;
        }

        return TeamOfficial::query()
            ->with(['team.event'])
            ->where('accreditation_code', $normalized)
            ->where('screening_status', 'passed')
            ->first();
    }
}
