<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditionAttendance;
use App\Models\AuditionParticipant;
use App\Models\AuditionStage;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AuditionAttendanceController extends Controller
{
    /**
     * Halaman Scanner Absensi Lapangan Multi-Hari untuk Petugas Screening / Gatekeeper.
     */
    public function scanner(Request $request): Response
    {
        $events = Event::query()
            ->where('participant_type', 'individual')
            ->orderByDesc('id')
            ->get(['id', 'name', 'slug', 'status', 'city', 'venue_name']);

        $selectedEventId = (int) $request->query('event_id', $events->first()?->id ?? 0);

        $stages = AuditionStage::query()
            ->when($selectedEventId > 0, fn ($q) => $q->where('event_id', $selectedEventId))
            ->orderBy('stage_number')
            ->get();

        $selectedStageId = (int) $request->query('stage_id', $stages->first()?->id ?? 0);

        $todayAttendancesCount = 0;
        if ($selectedStageId > 0) {
            $todayAttendancesCount = AuditionAttendance::query()
                ->where('audition_stage_id', $selectedStageId)
                ->where('status', 'present')
                ->count();
        }

        return Inertia::render('Admin/Auditions/Scanner', [
            'events' => $events,
            'selectedEventId' => $selectedEventId,
            'stages' => $stages,
            'selectedStageId' => $selectedStageId,
            'attendancesCount' => $todayAttendancesCount,
        ]);
    }

    /**
     * Memproses Verifikasi Gatekeeper Guard & Mencatat Kehadiran Peserta.
     */
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'stage_id' => ['required', 'integer', 'exists:audition_stages,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $code = trim($request->input('code'));
        /** @var AuditionStage $stage */
        $stage = AuditionStage::with('event')->findOrFail((int) $request->input('stage_id'));
        $event = $stage->event;

        // Cari peserta berdasarkan screening_code, registration_number, atau bib_number
        $participant = AuditionParticipant::query()
            ->where('event_id', $event->id)
            ->where(function ($q) use ($code) {
                $q->where('screening_code', $code)
                    ->orWhere('registration_number', $code);

                if (is_numeric($code)) {
                    $q->orWhere('bib_number', (int) $code);
                }
            })
            ->with(['eventSession', 'attendances'])
            ->first();

        if (! $participant) {
            return response()->json([
                'success' => false,
                'gatekeeper_status' => 'NOT_FOUND',
                'message' => "Kode '{$code}' tidak ditemukan pada event {$event->name}.",
                'participant' => null,
            ], 404);
        }

        // Gatekeeper Guard 1: Pembayaran Lunas
        if (! $participant->isPaid()) {
            return response()->json([
                'success' => false,
                'gatekeeper_status' => 'UNPAID',
                'message' => "AKSES DITOLAK (BELUM LUNAS): Peserta {$participant->full_name} belum menyelesaikan pembayaran registrasi.",
                'participant' => $this->formatParticipantPayload($participant),
            ], 422);
        }

        // Gatekeeper Guard 2: Screening Berkas Sah
        if (! $participant->isScreeningPassed()) {
            return response()->json([
                'success' => false,
                'gatekeeper_status' => 'SCREENING_FAILED',
                'message' => "AKSES DITOLAK (BELUM LOLOS SCREENING): Berkas dokumen fisik / akta {$participant->full_name} berstatus {$participant->screeningStatusLabel()}.",
                'participant' => $this->formatParticipantPayload($participant),
            ], 422);
        }

        // Gatekeeper Guard 3: Multi-Day Stage Progression & Anti-Eliminated Gate
        $stageNum = (int) $stage->stage_number;
        if ($stageNum === 2) {
            $allowedStatuses = ['day_1_passed', 'day_2_passed', 'final_selected'];
            if ($participant->stage_status === 'day_1_eliminated') {
                return response()->json([
                    'success' => false,
                    'gatekeeper_status' => 'ELIMINATED',
                    'message' => "AKSES DITOLAK (TERELIMINASI): Peserta {$participant->full_name} gugur pada penilaian Hari 1.",
                    'participant' => $this->formatParticipantPayload($participant),
                ], 422);
            }

            if (! in_array($participant->stage_status, $allowedStatuses, true)) {
                return response()->json([
                    'success' => false,
                    'gatekeeper_status' => 'NOT_PROMOTED',
                    'message' => "AKSES DITOLAK: Peserta {$participant->full_name} belum berstatus lolos ke Tahap 2.",
                    'participant' => $this->formatParticipantPayload($participant),
                ], 422);
            }
        } elseif ($stageNum >= 3) {
            $allowedStatuses = ['day_2_passed', 'final_selected'];
            if (in_array($participant->stage_status, ['day_1_eliminated', 'day_2_eliminated'], true)) {
                return response()->json([
                    'success' => false,
                    'gatekeeper_status' => 'ELIMINATED',
                    'message' => "AKSES DITOLAK (TERELIMINASI): Peserta {$participant->full_name} telah gugur pada tahap sebelumnya.",
                    'participant' => $this->formatParticipantPayload($participant),
                ], 422);
            }

            if (! in_array($participant->stage_status, $allowedStatuses, true)) {
                return response()->json([
                    'success' => false,
                    'gatekeeper_status' => 'NOT_PROMOTED',
                    'message' => "AKSES DITOLAK: Peserta {$participant->full_name} belum berstatus lolos ke Tahap Final.",
                    'participant' => $this->formatParticipantPayload($participant),
                ], 422);
            }
        }

        // Gatekeeper Guard 4: Cek Sudah Check-in Sebelumnya
        $existingAttendance = AuditionAttendance::query()
            ->where('audition_participant_id', $participant->id)
            ->where('audition_stage_id', $stage->id)
            ->first();

        if ($existingAttendance) {
            return response()->json([
                'success' => true,
                'already_checked_in' => true,
                'gatekeeper_status' => 'ALREADY_PRESENT',
                'message' => "PESERTA SUDAH CHECK-IN: {$participant->full_name} telah check-in pada pukul {$existingAttendance->check_in_at?->format('H:i:s')} WIB.",
                'check_in_at' => $existingAttendance->check_in_at?->format('H:i:s'),
                'participant' => $this->formatParticipantPayload($participant),
            ]);
        }

        // Catat Kehadiran Baru
        $attendance = AuditionAttendance::create([
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage->id,
            'scanned_by' => auth()->id(),
            'check_in_at' => now(),
            'status' => 'present',
            'notes' => $request->input('notes'),
        ]);

        $newCount = AuditionAttendance::query()
            ->where('audition_stage_id', $stage->id)
            ->where('status', 'present')
            ->count();

        return response()->json([
            'success' => true,
            'already_checked_in' => false,
            'gatekeeper_status' => 'CHECK_IN_SUCCESS',
            'message' => "CHECK-IN SAH: Selamat bertanding, {$participant->full_name} (Bib #{$participant->bib_number})!",
            'check_in_at' => $attendance->check_in_at?->format('H:i:s'),
            'total_attendances_count' => $newCount,
            'participant' => $this->formatParticipantPayload($participant),
        ]);
    }

    private function formatParticipantPayload(AuditionParticipant $p): array
    {
        $photoUrl = null;
        if ($p->photo_path) {
            $photoUrl = Storage::disk('public')->exists($p->photo_path)
                ? Storage::disk('public')->url($p->photo_path)
                : route('audition.birth-certificate', ['participant' => $p->id]);
        }

        return [
            'id' => $p->id,
            'full_name' => $p->full_name,
            'nickname' => $p->nickname,
            'bib_number' => $p->bib_number,
            'registration_number' => $p->registration_number,
            'screening_code' => $p->screening_code,
            'preferred_position' => $p->preferred_position,
            'current_club_ssb' => $p->current_club_ssb ?: 'Independen',
            'dominant_foot' => strtoupper($p->dominant_foot ?: '-'),
            'birth_date' => $p->birth_date?->format('d/m/Y'),
            'age' => $p->birth_date ? (int) $p->birth_date->diffInYears(now()) : null,
            'photo_url' => $photoUrl,
            'stage_status' => $p->stage_status,
            'stage_status_label' => $p->stageStatusLabel(),
            'screening_status' => $p->screening_status,
            'is_paid' => $p->isPaid(),
        ];
    }
}
