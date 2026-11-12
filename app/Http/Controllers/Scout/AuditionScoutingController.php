<?php

namespace App\Http\Controllers\Scout;

use App\Http\Controllers\Controller;
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
            ->whereNotNull('bib_number')
            ->with(['eventSession', 'attendances']);

        // Jika tahap 2 atau 3, bisa tetap tampilkan semua atau tandai yang lolos
        $participants = $participantsQuery
            ->orderBy('bib_number')
            ->get();

        // Ambil evaluasi milik scout yang sedang login pada tahap ini
        $scoutId = $request->user()->id;
        $evaluations = ScoutingEvaluation::query()
            ->where('audition_stage_id', $stage?->id ?? 0)
            ->where('scout_id', $scoutId)
            ->get()
            ->keyBy('audition_participant_id');

        $isSessionLocked = $evaluations->contains(fn ($e) => (bool) $e->is_locked);

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
    public function grade(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'audition_participant_id' => ['required', 'integer', 'exists:audition_participants,id'],
            'audition_stage_id' => ['required', 'integer', 'exists:audition_stages,id'],
            'quick_grade' => ['nullable', 'string', 'in:A,B,C'],
            'rating_stars' => ['nullable', 'integer', 'min:1', 'max:5'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'coach_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $scoutId = $request->user()->id;

        // Cek apakah sesi ini sudah dikunci oleh scout
        $existing = ScoutingEvaluation::query()
            ->where('audition_participant_id', $validated['audition_participant_id'])
            ->where('audition_stage_id', $validated['audition_stage_id'])
            ->where('scout_id', $scoutId)
            ->first();

        if ($existing && $existing->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'Penilaian tidak dapat diubah karena sesi scouting Anda untuk tahap ini telah dikunci.',
            ], 403);
        }

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
    public function lockSession(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'audition_stage_id' => ['required', 'integer', 'exists:audition_stages,id'],
        ]);

        $scoutId = $request->user()->id;

        ScoutingEvaluation::query()
            ->where('audition_stage_id', $validated['audition_stage_id'])
            ->where('scout_id', $scoutId)
            ->update(['is_locked' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Sesi penilaian berhasil dikunci secara permanen untuk tahap ini.',
        ]);
    }
}
