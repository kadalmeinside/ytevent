<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditionParticipant;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditionVerificationController extends Controller
{
    /**
     * Menampilkan profil verifikasi digital keabsahan peserta audisi melalui QR code.
     */
    public function verify(string $code): Response
    {
        $participant = AuditionParticipant::where('screening_code', $code)
            ->with(['event', 'eventSession', 'screeningOfficer'])
            ->first();

        if (! $participant || $participant->screening_status !== 'passed') {
            return Inertia::render('Public/AuditionVerification', [
                'isVerified' => false,
                'code' => $code,
                'participant' => null,
            ]);
        }

        $event = $participant->event;
        $maskedNik = $participant->nik;
        if (strlen($maskedNik) >= 16) {
            $maskedNik = substr($maskedNik, 0, 6) . '******' . substr($maskedNik, -4);
        }

        return Inertia::render('Public/AuditionVerification', [
            'isVerified' => true,
            'code' => $code,
            'participant' => [
                'full_name' => $participant->full_name,
                'nickname' => $participant->nickname,
                'bib_number' => $participant->bib_number,
                'registration_number' => $participant->registration_number,
                'preferred_position' => $participant->preferred_position,
                'secondary_position' => $participant->secondary_position,
                'dominant_foot' => $participant->dominant_foot,
                'birth_date' => $participant->birth_date?->format('d/m/Y'),
                'age' => $participant->birth_date ? (int) $participant->birth_date->diffInYears(now()) : null,
                'masked_nik' => $maskedNik,
                'height_cm' => $participant->height_cm,
                'weight_kg' => $participant->weight_kg,
                'current_club_ssb' => $participant->current_club_ssb ?: 'Independen',
                'playing_experience' => $participant->playing_experience,
                'stage_status' => $participant->stage_status,
                'stage_status_label' => $participant->stageStatusLabel(),
                'photo_url' => $participant->photo_path
                    ? route('public.audition.verify.photo', ['code' => $code])
                    : null,
                'screening_code' => $participant->screening_code,
                'screening_verified_at' => $participant->screening_verified_at
                    ? $participant->screening_verified_at->translatedFormat('d F Y, H:i') . ' WIB'
                    : null,
            ],
            'event' => [
                'name' => $event?->name,
                'age_category' => $event?->age_category,
                'venue_name' => $event?->venue_name,
                'city' => $event?->city,
                'session_name' => $participant->eventSession?->session_name,
            ],
        ]);
    }

    /**
     * Mengalirkan foto peserta audisi yang telah lolos verifikasi secara aman ke publik.
     */
    public function photo(string $code): StreamedResponse
    {
        $participant = AuditionParticipant::where('screening_code', $code)
            ->where('screening_status', 'passed')
            ->firstOrFail();

        if (! $participant->photo_path) {
            abort(404);
        }

        $disk = Storage::disk('public')->exists($participant->photo_path)
            ? Storage::disk('public')
            : (Storage::disk('private')->exists($participant->photo_path) ? Storage::disk('private') : null);

        if (! $disk) {
            abort(404);
        }

        return Storage::response($participant->photo_path);
    }
}

