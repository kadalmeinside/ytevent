<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CheckInAuditionParticipantRequest;
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
    public function checkIn(CheckInAuditionParticipantRequest $request): JsonResponse
    {
        $code = trim($request->input('code'));
        /** @var AuditionStage $stage */
        $stage = AuditionStage::with('event')->findOrFail((int) $request->input('stage_id'));
        $event = $stage->event;

        if (! $event || $event->participant_type !== 'individual') {
            return response()->json(['success' => false, 'gatekeeper_status' => 'STAGE_UNAVAILABLE',
                'message' => 'Event bukan kategori peserta perorangan.'], 422);
        }

        if (! $event->canRunFieldOperations()) {
            return response()->json(['success' => false, 'gatekeeper_status' => 'EVENT_UNAVAILABLE',
                'message' => 'Check-in hanya tersedia setelah pendaftaran ditutup atau saat event sedang berlangsung.'], 422);
        }

        if (! $stage->is_active || $stage->is_completed) {
            return response()->json(['success' => false, 'gatekeeper_status' => 'STAGE_UNAVAILABLE',
                'message' => 'Tahap audisi tidak sedang tersedia untuk check-in.'], 422);
        }

        $today = now()->toDateString();
        if ($stage->stage_date?->toDateString() !== $today) {
            return response()->json(['success' => false, 'gatekeeper_status' => 'OUTSIDE_CHECKIN_WINDOW',
                'message' => 'Check-in hanya dapat dilakukan pada tanggal tahap audisi.'], 422);
        }
        $currentTime = now()->format('H:i:s');
        $startTime = $stage->start_time ? strlen($stage->start_time) === 5 ? $stage->start_time . ':00' : $stage->start_time : null;
        $endTime = $stage->end_time ? strlen($stage->end_time) === 5 ? $stage->end_time . ':00' : $stage->end_time : null;
        if (($startTime && $currentTime < $startTime) || ($endTime && $currentTime > $endTime)) {
            return response()->json(['success' => false, 'gatekeeper_status' => 'OUTSIDE_CHECKIN_WINDOW',
                'message' => 'Check-in belum atau sudah melewati jam yang ditentukan.'], 422);
        }

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
        if ($stageNum === 1) {
            if ($participant->stage_status !== 'registered') {
                return response()->json(['success' => false, 'gatekeeper_status' => 'INVALID_STAGE_STATUS',
                    'message' => 'Status peserta tidak valid untuk Tahap 1.',
                    'participant' => $this->formatParticipantPayload($participant)], 422);
            }
        } elseif ($stageNum === 2) {
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
            if ($participant->stage_status !== 'day_2_passed' && $participant->stage_status !== 'final_selected') {
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
            if ($existingAttendance->status !== 'present') {
                return response()->json([
                    'success' => false,
                    'gatekeeper_status' => 'NOT_ELIGIBLE',
                    'message' => 'Peserta telah ditandai tidak hadir pada tahap ini.',
                    'participant' => $this->formatParticipantPayload($participant),
                ], 422);
            }
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
            'message' => "CHECK-IN SAH: Selamat hadir, {$participant->full_name} (ID Digital Pass: {$participant->registration_number})!",
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
                : ($p->screening_code ? route('public.audition.verify.photo', ['code' => $p->screening_code]) : null);
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
