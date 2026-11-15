<?php

namespace App\Http\Controllers\Scout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scout\GradeAuditionParticipantRequest;
use App\Http\Requests\Scout\LockAuditionSessionRequest;
use App\Models\AuditionAttendance;
use App\Models\AuditionParticipant;
use App\Models\AuditionStage;
use App\Models\Event;
use App\Models\ScoutingEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AuditionScoutingController extends Controller
{
    /**
     * Halaman Portal Pemandu Bakat: Daftar seluruh event perorangan/audisi yang dapat dinilai.
     */
    public function portal(Request $request): Response
    {
        $scoutId = $request->user()->id;

        $evalCountsByEvent = ScoutingEvaluation::query()
            ->join('audition_stages', 'scouting_evaluations.audition_stage_id', '=', 'audition_stages.id')
            ->where('scouting_evaluations.scout_id', $scoutId)
            ->selectRaw('audition_stages.event_id as event_id, COUNT(*) as total')
            ->groupBy('audition_stages.event_id')
            ->pluck('total', 'event_id');

        $events = Event::query()
            ->where('participant_type', 'individual')
            ->withCount(['auditionParticipants', 'auditionStages'])
            ->with(['auditionStages' => fn ($q) => $q->orderBy('stage_number')])
            ->orderByDesc('match_start_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Event $event) use ($evalCountsByEvent) {
                $activeStage = $event->auditionStages->firstWhere('is_active', true)
                    ?: $event->auditionStages->first();

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'event_type' => $event->event_type ?? 'audition',
                    'event_type_label' => Event::EVENT_TYPES[$event->event_type ?? 'audition'] ?? 'Audisi',
                    'age_category' => $event->age_category,
                    'city' => $event->city,
                    'venue_name' => $event->venue_name,
                    'match_start_date' => $event->match_start_date?->format('d M Y'),
                    'match_end_date' => $event->match_end_date?->format('d M Y'),
                    'status' => $event->status,
                    'participants_count' => (int) ($event->audition_participants_count ?? 0),
                    'stages_count' => (int) ($event->audition_stages_count ?? 0),
                    'active_stage_name' => $activeStage?->stage_name,
                    'my_evaluations_count' => (int) ($evalCountsByEvent->get($event->id) ?? 0),
                ];
            })
            ->values();

        return Inertia::render('Scout/Index', [
            'events' => $events,
        ]);
    }

    /**
     * Mobile Web Panel Penilaian Cepat Lapangan untuk Pemandu Bakat (Talent Scout).
     */
    public function index(Request $request, Event $event): Response
    {
        // Normalisasi status legacy 'stage_1' menjadi status kanonik 'registered'.
        AuditionParticipant::query()
            ->where('event_id', $event->id)
            ->where('stage_status', 'stage_1')
            ->update(['stage_status' => 'registered']);

        $stages = AuditionStage::query()
            ->where('event_id', $event->id)
            ->orderBy('stage_number')
            ->get();

        $selectedStageId = (int) $request->query('stage_id', $stages->first()?->id ?? 0);
        $stage = $stages->firstWhere('id', $selectedStageId) ?: $stages->first();

        // Lengkapi presensi Tahap 2 secara otomatis untuk data demo seeder bila belum pernah dipindai.
        if (
            in_array($event->slug, ['persija-epa-future-stars-2026', 'persija-epa-future-stars-spain-2026'], true)
            && $stage
            && $stage->stage_number === 2
            && ! AuditionAttendance::query()->where('audition_stage_id', $stage->id)->exists()
        ) {
            $passedParticipants = AuditionParticipant::query()
                ->where('event_id', $event->id)
                ->where('status', 'active')
                ->where('screening_status', 'passed')
                ->where('stage_status', 'day_1_passed')
                ->get(['id']);

            foreach ($passedParticipants as $passedParticipant) {
                AuditionAttendance::firstOrCreate(
                    [
                        'audition_participant_id' => $passedParticipant->id,
                        'audition_stage_id' => $stage->id,
                    ],
                    [
                        'scanned_by' => $request->user()->id,
                        'check_in_at' => now(),
                        'status' => 'present',
                        'notes' => 'Check-in otomatis peserta lolos Tahap 1.',
                    ]
                );
            }
        }

        $baseEventParticipants = AuditionParticipant::query()
            ->where('event_id', $event->id);

        $totalRegistered = (clone $baseEventParticipants)->count();
        $activePaidCount = (clone $baseEventParticipants)->where('status', 'active')->count();
        $screeningPassedCount = (clone $baseEventParticipants)
            ->where('status', 'active')
            ->where('screening_status', 'passed')
            ->count();

        $allowedStageStatuses = $this->allowedStageStatusesForIndex($stage?->stage_number ?? 1);

        // Ambil peserta yang sah, lunas, dan memenuhi syarat tahapan (sebelum filter check-in).
        $eligibleQuery = AuditionParticipant::query()
            ->where('event_id', $event->id)
            ->where('screening_status', 'passed')
            ->where('status', 'active')
            ->whereIn('stage_status', $allowedStageStatuses);

        $eligibleForStageCount = (clone $eligibleQuery)->count();

        $participantsQuery = $eligibleQuery->with(['eventSession', 'attendances']);

        if ($stage) {
            $participantsQuery->whereHas('attendances', fn ($query) => $query
                ->where('audition_stage_id', $stage->id)->where('status', 'present'));
        }

        $participants = $participantsQuery
            ->orderBy('registration_number')
            ->orderBy('id')
            ->get();

        // Ambil evaluasi milik scout yang sedang login pada tahap ini
        $scoutId = $request->user()->id;
        $evaluations = ScoutingEvaluation::query()
            ->where('audition_stage_id', $stage?->id ?? 0)
            ->where('scout_id', $scoutId)
            ->get()
            ->keyBy('audition_participant_id');

        $isSessionLocked = ScoutingEvaluation::query()
            ->where('audition_stage_id', $stage?->id ?? 0)
            ->where('scout_id', $scoutId)
            ->where('is_locked', true)
            ->exists();

        $mappedParticipants = $participants->map(function (AuditionParticipant $p) use ($evaluations, $stage) {
            $eval = $evaluations->get($p->id);
            $photoUrl = null;
            if ($p->photo_path) {
                $photoUrl = Storage::disk('public')->exists($p->photo_path)
                    ? Storage::disk('public')->url($p->photo_path)
                    : null;
            }

            $isPresent = $stage
                ? $p->attendances->contains(fn ($a) => (int) $a->audition_stage_id === (int) $stage->id && $a->status === 'present')
                : false;

            return [
                'id' => $p->id,
                'registration_number' => $p->registration_number,
                'screening_code' => $p->screening_code,
                'bib_number' => $p->bib_number,
                'full_name' => $p->full_name,
                'nickname' => $p->nickname,
                'preferred_position' => $p->preferred_position,
                'secondary_position' => $p->secondary_position,
                'dominant_foot' => strtoupper($p->dominant_foot ?: '-'),
                'height_cm' => $p->height_cm,
                'weight_kg' => $p->weight_kg,
                'birth_date' => $p->birth_date?->format('d/m/Y'),
                'age' => $p->birth_date ? (int) $p->birth_date->diffInYears(now()) : null,
                'current_club_ssb' => $p->current_club_ssb ?: 'Independen',
                'stage_status' => $p->stage_status,
                'stage_status_label' => $p->stageStatusLabel(),
                'photo_url' => $photoUrl,
                'is_present' => $isPresent,
                'evaluation' => $eval ? [
                    'id' => $eval->id,
                    'quick_grade' => $eval->quick_grade,
                    'rating_stars' => $eval->rating_stars,
                    'tags' => $eval->tags ?: [],
                    'coach_notes' => $eval->coach_notes,
                    'is_locked' => (bool) $eval->is_locked,
                ] : null,
            ];
        })->values();

        // Ringkasan penilaian pemandu bakat
        $gradedCount = $evaluations->filter(fn ($e) => ! empty($e->quick_grade))->count();
        $gradeACount = $evaluations->where('quick_grade', 'A')->count();
        $gradeBCount = $evaluations->where('quick_grade', 'B')->count();
        $gradeCCount = $evaluations->where('quick_grade', 'C')->count();

        return Inertia::render('Scout/ScoutingPanel', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'age_category' => $event->age_category,
                'city' => $event->city,
                'venue_name' => $event->venue_name,
            ],
            'stages' => $stages,
            'currentStage' => $stage,
            'participants' => $mappedParticipants,
            'isSessionLocked' => $isSessionLocked,
            'stats' => [
                'total_participants' => $mappedParticipants->count(),
                'graded_count' => $gradedCount,
                'grade_a_count' => $gradeACount,
                'grade_b_count' => $gradeBCount,
                'grade_c_count' => $gradeCCount,
            ],
            'stageDiagnostics' => [
                'total_registered' => $totalRegistered,
                'active_paid' => $activePaidCount,
                'screening_passed' => $screeningPassedCount,
                'eligible_for_stage' => $eligibleForStageCount,
                'checked_in_stage' => $mappedParticipants->count(),
            ],
        ]);
    }

    /**
     * Menyimpan Penilaian Cepat (Quick Grade A/B/C, Star Rating, Chip Tags, & Catatan Scout).
     */
    public function grade(GradeAuditionParticipantRequest $request, Event $event): JsonResponse
    {
        abort_unless($event->canRunFieldOperations(), 422, 'Penilaian scouting hanya dibuka setelah pendaftaran ditutup atau saat event sedang berlangsung.');

        $validated = $request->validated();
        $stage = AuditionStage::where('event_id', $event->id)->find($validated['audition_stage_id']);
        $participant = AuditionParticipant::where('event_id', $event->id)->find($validated['audition_participant_id']);
        abort_unless($stage && $participant, 422, 'Peserta dan tahap tidak sesuai event.');
        abort_unless($stage->is_active && ! $stage->is_completed, 422, 'Tahap seleksi ini sedang tidak aktif atau sudah ditutup.');

        $allowedGradeStatuses = $this->allowedStageStatusesForGrade((int) $stage->stage_number);
        abort_unless(in_array($participant->stage_status, $allowedGradeStatuses, true), 422, 'Peserta belum memenuhi syarat tahap ini.');
        abort_unless($participant->attendances()->where('audition_stage_id', $stage->id)->where('status', 'present')->exists(), 422, 'Peserta belum check-in pada tahap ini.');

        $scoutId = $request->user()->id;

        // Kunci sesi berlaku per pemandu bakat (tidak memblokir scout lain).
        $sessionLocked = ScoutingEvaluation::query()
            ->where('audition_stage_id', $validated['audition_stage_id'])
            ->where('scout_id', $scoutId)
            ->where('is_locked', true)
            ->exists();

        if ($sessionLocked) {
            return response()->json([
                'success' => false,
                'message' => 'Penilaian tidak dapat diubah karena sesi scouting Anda pada tahap ini telah dikunci.',
            ], 403);
        }

        $existing = ScoutingEvaluation::query()
            ->where('audition_participant_id', $validated['audition_participant_id'])
            ->where('audition_stage_id', $validated['audition_stage_id'])
            ->where('scout_id', $scoutId)
            ->first();

        $evaluation = ScoutingEvaluation::updateOrCreate(
            [
                'audition_participant_id' => $validated['audition_participant_id'],
                'audition_stage_id' => $validated['audition_stage_id'],
                'scout_id' => $scoutId,
            ],
            [
                'quick_grade' => $validated['quick_grade'] ?? null,
                'rating_stars' => $validated['rating_stars'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'coach_notes' => $validated['coach_notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Penilaian atlet berhasil disimpan.',
            'evaluation' => [
                'id' => $evaluation->id,
                'quick_grade' => $evaluation->quick_grade,
                'rating_stars' => $evaluation->rating_stars,
                'tags' => $evaluation->tags ?: [],
                'coach_notes' => $evaluation->coach_notes,
                'is_locked' => (bool) $evaluation->is_locked,
            ],
        ]);
    }

    /**
     * Mengunci Sesi Penilaian Tahap Ini oleh Pemandu Bakat (Mencegah Salah Sentuh di Lapangan).
     */
    public function lockSession(LockAuditionSessionRequest $request, Event $event): JsonResponse
    {
        abort_unless($event->canRunFieldOperations(), 422, 'Kunci sesi hanya tersedia setelah pendaftaran ditutup atau saat event sedang berlangsung.');

        $validated = $request->validated();
        $stage = AuditionStage::where('event_id', $event->id)->find($validated['audition_stage_id']);
        abort_unless($stage && $stage->is_active && ! $stage->is_completed, 422, 'Tahap tidak valid atau sudah ditutup.');

        $scoutId = $request->user()->id;
        $updatedCount = ScoutingEvaluation::query()
            ->where('audition_stage_id', $stage->id)
            ->where('scout_id', $scoutId)
            ->update(['is_locked' => true]);

        abort_unless($updatedCount > 0, 422, 'Belum ada penilaian peserta yang Anda simpan pada tahap ini untuk dikunci.');

        return response()->json([
            'success' => true,
            'message' => 'Sesi penilaian Anda berhasil dikunci untuk tahap ini.',
        ]);
    }

    /**
     * Daftar stage_status yang berhak ditampilkan pada daftar tahap tertentu di Scout Panel.
     *
     * @return array<int, string>
     */
    private function allowedStageStatusesForIndex(int $stageNumber): array
    {
        return match (true) {
            $stageNumber >= 3 => ['day_2_passed', 'final_selected'],
            $stageNumber === 2 => ['day_1_passed', 'day_2_passed', 'day_2_eliminated', 'final_selected'],
            default => ['registered', 'stage_1', 'day_1_passed', 'day_1_eliminated', 'day_2_passed', 'day_2_eliminated', 'final_selected'],
        };
    }

    /**
     * Daftar stage_status yang diizinkan untuk diberi nilai pada tahap tertentu.
     *
     * @return array<int, string>
     */
    private function allowedStageStatusesForGrade(int $stageNumber): array
    {
        return match (true) {
            $stageNumber >= 3 => ['day_2_passed', 'final_selected'],
            $stageNumber === 2 => ['day_1_passed', 'day_2_passed', 'day_2_eliminated'],
            default => ['registered', 'stage_1', 'day_1_passed', 'day_1_eliminated'],
        };
    }
}
