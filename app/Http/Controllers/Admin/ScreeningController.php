<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerifyDocumentRequest;
use App\Http\Requests\Admin\VerifyPlayerRequest;
use App\Models\Event;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Services\ScreeningService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScreeningController extends Controller
{
    public function __construct(
        protected ScreeningService $screeningService
    ) {}

    /**
     * Menampilkan daftar antrean tim dan status pemeriksaan berkas per event.
     */
    public function index(Request $request): Response
    {
        $eventId = $request->query('event_id');
        $status = $request->query('status');
        $search = $request->query('search');

        $events = Event::query()
            ->orderByDesc('id')
            ->get(['id', 'name', 'slug', 'age_category', 'min_players_per_team', 'max_players_per_team']);

        $teamsQuery = Team::query()
            ->with([
                'event:id,name,slug,age_category,min_players_per_team,max_players_per_team',
                'user:id,name,email,phone',
            ])
            ->withCount([
                'players',
                'players as submitted_players_count' => fn ($q) => $q->where('screening_status', 'submitted'),
                'players as passed_players_count' => fn ($q) => $q->where('screening_status', 'passed'),
                'players as rejected_players_count' => fn ($q) => $q->where('screening_status', 'rejected'),
                'players as draft_players_count' => fn ($q) => $q->where('screening_status', 'draft'),
            ])
            ->where('status', 'active'); // Hanya tim aktif / lunas yang dapat di-screening

        if ($eventId) {
            $teamsQuery->where('event_id', $eventId);
        }

        if ($search) {
            $teamsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('manager_name', 'like', "%{$search}%")
                    ->orWhere('origin_association', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['in_review', 'partially_rejected', 'all_passed', 'not_started'])) {
            $teamsQuery->where('screening_status', $status);
        }

        $teams = $teamsQuery
            ->orderByRaw("CASE screening_status WHEN 'in_review' THEN 1 WHEN 'partially_rejected' THEN 2 WHEN 'all_passed' THEN 3 WHEN 'not_started' THEN 4 ELSE 5 END")
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'logo_url' => $team->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($team->logo_path) : null,
                'origin_association' => $team->origin_association,
                'manager_name' => $team->manager_name,
                'manager_phone' => $team->manager_whatsapp,
                'manager_email' => $team->manager_email,
                'head_coach_name' => $team->head_coach_name,
                'screening_status' => $team->screening_status,
                'event' => [
                    'id' => $team->event->id,
                    'name' => $team->event->name,
                    'slug' => $team->event->slug,
                    'age_category' => $team->event->age_category,
                    'min_players' => $team->event->min_players_per_team,
                    'max_players' => $team->event->max_players_per_team,
                ],
                'stats' => [
                    'total_players' => $team->players_count,
                    'submitted' => $team->submitted_players_count,
                    'passed' => $team->passed_players_count,
                    'rejected' => $team->rejected_players_count,
                    'draft' => $team->draft_players_count,
                    'verified_percentage' => $team->players_count > 0
                        ? round(($team->passed_players_count / $team->players_count) * 100)
                        : 0,
                ],
                'updated_at' => $team->updated_at?->format('d M Y, H:i'),
            ]);

        // Hitung total ringkasan untuk kartu metrik atas
        $baseCountQuery = Team::query()->where('status', 'active');
        if ($eventId) {
            $baseCountQuery->where('event_id', $eventId);
        }

        $metrics = [
            'total_teams_active' => (clone $baseCountQuery)->count(),
            'teams_in_review' => (clone $baseCountQuery)->where('screening_status', 'in_review')->count(),
            'teams_partially_rejected' => (clone $baseCountQuery)->where('screening_status', 'partially_rejected')->count(),
            'teams_all_passed' => (clone $baseCountQuery)->where('screening_status', 'all_passed')->count(),
            'total_submitted_players' => Player::whereHas('team', fn ($q) => $eventId ? $q->where('event_id', $eventId) : $q)->where('screening_status', 'submitted')->count(),
        ];

        return Inertia::render('Admin/Screening/Index', [
            'events' => $events,
            'teams' => $teams,
            'metrics' => $metrics,
            'filters' => [
                'event_id' => $eventId ? (int) $eventId : null,
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Membuka panel Split-View pemeriksaan berkas seluruh pemain dari tim tertentu.
     */
    public function teamReview(Event $event, Team $team): Response|RedirectResponse
    {
        if ($team->event_id !== $event->id) {
            return redirect()->route('admin.screening.index')->with('error', 'Tim tidak terdaftar pada event yang dipilih.');
        }

        $team->load(['user', 'event']);

        $players = $team->players()
            ->with(['documents', 'screeningLogs.actor:id,name'])
            ->orderBy('jersey_number')
            ->get()
            ->map(function (Player $player) use ($event) {
                return [
                    'id' => $player->id,
                    'full_name' => $player->full_name,
                    'jersey_name' => $player->jersey_name,
                    'jersey_number' => $player->jersey_number,
                    'position' => $player->position,
                    'position_label' => Player::POSITIONS[$player->position] ?? $player->position,
                    'dominant_foot' => $player->dominant_foot,
                    'birth_place' => $player->birth_place,
                    'birth_date' => $player->birth_date?->format('Y-m-d'),
                    'birth_date_formatted' => $player->birth_date?->format('d M Y'),
                    'age' => $player->birth_date ? Carbon::parse($player->birth_date)->age : null,
                    'is_age_valid' => $event->isBirthDateEligible($player->birth_date?->format('Y-m-d')),
                    'nik' => $player->nik,
                    'nisn' => $player->nisn,
                    'height_cm' => $player->height_cm,
                    'weight_kg' => $player->weight_kg,
                    'photo_url' => $player->photo_path ? route('private.player-photo', ['player' => $player->id]) : null,
                    'screening_status' => $player->screening_status,
                    'is_locked' => (bool) $player->is_locked,
                    'rejection_reason' => $player->rejection_reason,
                    'accreditation_code' => $player->accreditation_code,
                    'verified_at' => $player->verified_at?->format('d M Y, H:i'),
                    'documents' => $player->documents->map(fn (PlayerDocument $doc) => [
                        'id' => $doc->id,
                        'document_type' => $doc->document_type,
                        'label' => Event::documentLabel($doc->document_type),
                        'document_number' => $doc->document_number,
                        'original_name' => $doc->original_name,
                        'file_size' => $doc->file_size,
                        'mime_type' => $doc->mime_type,
                        'is_pdf' => $doc->mime_type === 'application/pdf' || str_ends_with(strtolower($doc->original_name ?? ''), '.pdf'),
                        'validation_status' => $doc->validation_status,
                        'notes' => $doc->notes,
                        'preview_url' => route('private.player-document', ['document' => $doc->id]),
                    ]),
                    'logs' => $player->screeningLogs->sortByDesc('id')->values()->take(5)->map(fn ($log) => [
                        'id' => $log->id,
                        'actor_name' => $log->actor?->name ?? 'Sistem',
                        'from_status' => $log->from_status,
                        'to_status' => $log->to_status,
                        'reason' => $log->reason,
                        'created_at' => $log->created_at?->format('d M Y, H:i'),
                    ]),
                ];
            });

        return Inertia::render('Admin/Screening/TeamReview', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'age_category' => $event->age_category,
                'min_birth_date' => $event->min_birth_date?->format('d M Y'),
                'max_birth_date' => $event->max_birth_date?->format('d M Y'),
                'min_players' => $event->min_players_per_team,
                'max_players' => $event->max_players_per_team,
                'required_documents' => $event->required_documents,
            ],
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'logo_url' => $team->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($team->logo_path) : null,
                'origin_association' => $team->origin_association,
                'manager_name' => $team->manager_name,
                'manager_phone' => $team->manager_whatsapp,
                'manager_email' => $team->manager_email,
                'head_coach_name' => $team->head_coach_name,
                'screening_status' => $team->screening_status,
            ],
            'players' => $players,
        ]);
    }

    /**
     * Memproses keputusan verifikasi pemain (Loloskan / Tolak).
     */
    public function verifyPlayer(VerifyPlayerRequest $request, Player $player): RedirectResponse
    {
        $action = $request->input('action');
        $reason = $request->input('rejection_reason');
        $docUpdates = $request->input('documents', []);

        $updatedPlayer = $this->screeningService->verifyPlayer(
            $player,
            $request->user(),
            $action,
            $reason,
            $docUpdates
        );

        $msg = $action === 'passed'
            ? "Pemain {$updatedPlayer->full_name} dinyatakan SAH (Lolos Verifikasi)."
            : "Berkas pemain {$updatedPlayer->full_name} DITOLAK dengan catatan revisi.";

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Memverifikasi satu berkas dokumen pemain.
     */
    public function verifyDocument(VerifyDocumentRequest $request, PlayerDocument $document): JsonResponse|RedirectResponse
    {
        $status = $request->input('validation_status');
        $notes = $request->input('notes');

        $doc = $this->screeningService->verifyDocument(
            $document,
            $request->user(),
            $status,
            $notes
        );

        $player = $doc->player;

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Status dokumen {$document->label} berhasil diperbarui.",
                'document' => [
                    'id' => $document->id,
                    'validation_status' => $status,
                    'notes' => $notes,
                ],
                'player' => $player ? [
                    'id' => $player->id,
                    'screening_status' => $player->screening_status,
                    'is_locked' => $player->is_locked,
                    'rejection_reason' => $player->rejection_reason,
                ] : null,
            ]);
        }

        return redirect()->back()->with('success', "Status dokumen {$document->label} berhasil diperbarui.");
    }

    /**
     * Membuka kembali kunci pemain yang sudah lolos (Emergency Unlock).
     */
    public function unlockPlayer(Request $request, Player $player): RedirectResponse
    {
        if (! $request->user()?->can('screening.unlock_player')) {
            abort(403, 'Anda tidak memiliki wewenang untuk membuka kunci data pemain.');
        }

        $reason = $request->input('reason');
        $this->screeningService->unlockPlayer($player, $request->user(), $reason);

        return redirect()->back()->with('success', "Kunci pemain {$player->full_name} berhasil dibuka dan dikembalikan ke draft.");
    }
}
