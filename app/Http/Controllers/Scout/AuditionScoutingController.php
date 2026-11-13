<?php

namespace App\Http\Controllers\Scout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scout\GradeAuditionParticipantRequest;
use App\Http\Requests\Scout\LockAuditionSessionRequest;
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
     * Mobile Web Panel Penilaian Cepat Lapangan untuk Pemandu Bakat (Talent Scout).
     */
    public function index(Request $request, Event $event): Response
    {
        $stages = AuditionStage::query()
            ->where('event_id', $event->id)
            ->orderBy('stage_number')
            ->get();

        $selectedStageId = (int) $request->query('stage_id', $stages->first()?->id ?? 0);
        $stage = $stages->firstWhere('id', $selectedStageId) ?: $stages->first();

        // Ambil peserta yang sah & lunas
        $participantsQuery = AuditionParticipant::query()
            ->where('event_id', $event->id)
            ->where('screening_status', 'passed')
            ->where('status', 'active')
            ->with(['eventSession', 'attendances']);

        // Jika tahap 2 atau 3, bisa tetap tampilkan semua atau tandai yang lolos
        if ($stage && $stage->stage_number > 1) {
            $requiredStatus = $stage->stage_number === 2 ? 'day_1_passed' : 'day_2_passed';
            $participantsQuery->where('stage_status', $requiredStatus);
        } else {
            $participantsQuery->where('stage_status', 'registered');
        }
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
        ]);
    }

    /**
     * Menyimpan Penilaian Cepat (Quick Grade A/B/C, Star Rating, Chip Tags, & Catatan Scout).
     */
    public function grade(GradeAuditionParticipantRequest $request, Event $event): JsonResponse
    {
        $validated = $request->validated();
        $stage = AuditionStage::where('event_id', $event->id)->find($validated['audition_stage_id']);
        $participant = AuditionParticipant::where('event_id', $event->id)->find($validated['audition_participant_id']);
        abort_unless($stage && $participant, 422, 'Peserta dan tahap tidak sesuai event.');
        $requiredStatus = $stage->stage_number === 1 ? 'registered' : ($stage->stage_number === 2 ? 'day_1_passed' : 'day_2_passed');
        abort_unless($participant->stage_status === $requiredStatus, 422, 'Peserta belum memenuhi syarat tahap ini.');
        abort_unless($participant->attendances()->where('audition_stage_id', $stage->id)->where('status', 'present')->exists(), 422, 'Peserta belum check-in pada tahap ini.');

        $scoutId = $request->user()->id;

        // Sesi dikunci secara global setelah konsolidasi tahap dimulai.
        $sessionLocked = ScoutingEvaluation::query()
            ->where('audition_stage_id', $validated['audition_stage_id'])
            ->where('is_locked', true)
            ->exists();

        if ($sessionLocked) {
            return response()->json([
                'success' => false,
                'message' => 'Penilaian tidak dapat diubah karena sesi scouting tahap ini telah dikunci.',
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
        $validated = $request->validated();
        abort_unless(AuditionStage::where('event_id', $event->id)->whereKey($validated['audition_stage_id'])->exists(), 422, 'Tahap tidak sesuai event.');

        ScoutingEvaluation::query()
            ->where('audition_stage_id', $validated['audition_stage_id'])
            ->update(['is_locked' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Sesi penilaian berhasil dikunci secara permanen untuk tahap ini.',
        ]);
    }
}
