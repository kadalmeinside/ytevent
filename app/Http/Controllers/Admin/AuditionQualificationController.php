<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AuditionStageResultMail;
use App\Models\AuditLog;
use App\Models\AuditionParticipant;
use App\Models\AuditionStage;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AuditionQualificationController extends Controller
{
    /**
     * Panel Konsolidasi Nilai Scout & Pengambilan Keputusan Kelolosan Bertahap.
     */
    public function index(Request $request, Event $event): Response
    {
        $stages = AuditionStage::query()
            ->where('event_id', $event->id)
            ->orderBy('stage_number')
            ->get();

        $participants = AuditionParticipant::query()
            ->where('event_id', $event->id)
            ->where('screening_status', 'passed')
            ->where('status', 'active')
            ->whereNotNull('bib_number')
            ->with(['evaluations.scout', 'attendances.stage'])
            ->orderBy('bib_number')
            ->get()
            ->map(function (AuditionParticipant $p) {
                $evals = $p->evaluations;

                $totalEvals = $evals->count();
                $gradeA = $evals->where('quick_grade', 'A')->count();
                $gradeB = $evals->where('quick_grade', 'B')->count();
                $gradeC = $evals->where('quick_grade', 'C')->count();

                $avgStars = $totalEvals > 0 ? round($evals->avg('rating_stars'), 1) : 0;
                $allTags = $evals->pluck('tags')->flatten()->filter()->unique()->values()->all();

                $notesList = $evals->filter(fn ($e) => ! empty($e->coach_notes))
                    ->map(fn ($e) => [
                        'scout_name' => $e->scout?->name ?: 'Scout',
                        'note' => $e->coach_notes,
                    ])->values()->all();

                $attendedStages = $p->attendances
                    ->where('status', 'present')
                    ->pluck('audition_stage_id')
                    ->all();

                $photoUrl = null;
                if ($p->photo_path) {
                    $photoUrl = Storage::disk('public')->exists($p->photo_path)
                        ? Storage::disk('public')->url($p->photo_path)
                        : null;
                }

                return [
                    'id' => $p->id,
                    'bib_number' => $p->bib_number,
                    'full_name' => $p->full_name,
                    'nickname' => $p->nickname,
                    'preferred_position' => $p->preferred_position,
                    'dominant_foot' => strtoupper($p->dominant_foot ?: '-'),
                    'current_club_ssb' => $p->current_club_ssb ?: 'Independen',
                    'birth_date' => $p->birth_date?->format('d/m/Y'),
                    'age' => $p->birth_date ? (int) $p->birth_date->diffInYears(now()) : null,
                    'stage_status' => $p->stage_status,
                    'stage_status_label' => $p->stageStatusLabel(),
                    'photo_url' => $photoUrl,
                    'attended_stages' => $attendedStages,
                    'consensus' => [
                        'total_scouts' => $totalEvals,
                        'grade_a_count' => $gradeA,
                        'grade_b_count' => $gradeB,
                        'grade_c_count' => $gradeC,
                        'avg_stars' => $avgStars,
                        'tags' => $allTags,
                        'notes' => $notesList,
                    ],
                ];
            });

        return Inertia::render('Admin/Auditions/Qualifications', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'age_category' => $event->age_category,
                'city' => $event->city,
                'venue_name' => $event->venue_name,
            ],
            'stages' => $stages,
            'participants' => $participants,
            'stageStatuses' => AuditionParticipant::STAGE_STATUSES,
        ]);
    }

    /**
     * Memproses Promosi Kelolosan atau Eliminasi Peserta Audisi Bertahap secara Masal.
     */
    public function advanceStage(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:audition_participants,id'],
            'target_stage_status' => ['required', 'string', 'in:registered,day_1_passed,day_1_eliminated,day_2_passed,day_2_eliminated,final_selected'],
            'dispatch_email' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $ids = $validated['participant_ids'];
        $targetStatus = $validated['target_stage_status'];
        $dispatchEmail = (bool) ($validated['dispatch_email'] ?? true);
        $customNotes = $validated['notes'] ?? null;

        $isPassed = ! str_ends_with($targetStatus, '_eliminated');

        $participants = AuditionParticipant::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->with(['user', 'event'])
            ->get();

        foreach ($participants as $participant) {
            $oldStatus = $participant->stage_status;
            $participant->update([
                'stage_status' => $targetStatus,
            ]);

            // Dispatch transactional email jika diaktifkan
            if ($dispatchEmail && $participant->user?->email) {
                try {
                    Mail::to($participant->user->email)->send(
                        new AuditionStageResultMail(
                            participant: $participant,
                            resultStatus: $isPassed ? 'passed' : 'eliminated',
                            customNotes: $customNotes,
                        )
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        // Catat Audit Trail
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'audition.stage.advanced',
            'auditable_type' => Event::class,
            'auditable_id' => $event->id,
            'description' => sprintf(
                'Mengubah status kelolosan %d peserta audisi menjadi "%s" pada event "%s"',
                count($ids),
                AuditionParticipant::STAGE_STATUSES[$targetStatus] ?? $targetStatus,
                $event->name
            ),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => [
                'participant_ids' => $ids,
                'target_stage_status' => $targetStatus,
                'dispatch_email' => $dispatchEmail,
            ],
        ]);

        $statusLabel = AuditionParticipant::STAGE_STATUSES[$targetStatus] ?? $targetStatus;
        return back()->with('success', "Berhasil memperbarui status " . count($ids) . " peserta menjadi '{$statusLabel}'. Email pengumuman telah dikirim.");
    }
}
