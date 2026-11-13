<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UnlockAuditionParticipantRequest;
use App\Http\Requests\Admin\VerifyAuditionDocumentRequest;
use App\Http\Requests\Admin\VerifyAuditionParticipantRequest;
use App\Http\Requests\Admin\VerifyDocumentRequest;
use App\Http\Requests\Admin\VerifyPlayerRequest;
use App\Models\AuditLog;
use App\Models\AuditionParticipant;
use App\Models\Event;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Services\ScreeningService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /**
     * Menampilkan daftar antrean verifikasi berkas peserta event perorangan (Audisi / Coaching Clinic).
     */
    public function auditionsIndex(Request $request): Response
    {
        $eventId = $request->query('event_id');
        $status = $request->query('status');
        $search = $request->query('search');

        $events = Event::query()
            ->where(function ($q) {
                $q->where('participant_type', 'individual')
                    ->orWhereIn('event_type', ['audition', 'coaching_clinic']);
            })
            ->orderByDesc('id')
            ->get(['id', 'name', 'slug', 'age_category', 'event_type']);

        $query = AuditionParticipant::query()
            ->with([
                'event:id,name,slug,age_category,event_type,min_birth_date,max_birth_date',
                'user:id,name,email,phone',
                'eventSession:id,session_name',
                'latestPayment',
            ]);

        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['submitted', 'passed', 'rejected', 'draft'], true)) {
            $query->where('screening_status', $status);
        }

        $participants = $query
            ->orderByRaw("CASE screening_status WHEN 'submitted' THEN 1 WHEN 'rejected' THEN 2 WHEN 'passed' THEN 3 ELSE 4 END")
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (AuditionParticipant $p) {
                $docs = $p->getStructuredDocuments();
                $validDocsCount = collect($docs)->where('validation_status', 'valid')->count();
                $invalidDocsCount = collect($docs)->where('validation_status', 'invalid')->count();

                return [
                    'id' => $p->id,
                    'registration_number' => $p->registration_number,
                    'bib_number' => $p->bib_number,
                    'full_name' => $p->full_name,
                    'nickname' => $p->nickname,
                    'nik' => $p->nik,
                    'birth_date' => $p->birth_date?->format('d/m/Y'),
                    'is_age_valid' => $p->event ? $p->event->isBirthDateEligible($p->birth_date?->format('Y-m-d')) : true,
                    'gender' => $p->gender,
                    'preferred_position' => $p->preferred_position,
                    'dominant_foot' => $p->dominant_foot,
                    'height_cm' => $p->height_cm,
                    'weight_kg' => $p->weight_kg,
                    'current_club_ssb' => $p->current_club_ssb,
                    'jersey_size' => $p->jersey_size,
                    'emergency_contact_name' => $p->emergency_contact_name,
                    'emergency_contact_phone' => $p->emergency_contact_phone,
                    'screening_status' => $p->screening_status,
                    'screening_status_label' => $p->screeningStatusLabel(),
                    'screening_notes' => $p->screening_notes,
                    'stage_status' => $p->stage_status,
                    'stage_status_label' => $p->stageStatusLabel(),
                    'status' => $p->status,
                    'is_paid' => $p->isPaid(),
                    'can_download_bib' => $p->canDownloadBib(),
                    'docs_total' => count($docs),
                    'docs_valid' => $validDocsCount,
                    'docs_invalid' => $invalidDocsCount,
                    'photo_url' => $p->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
                    'birth_cert_url' => route('private.audition.birth-certificate', ['participant' => $p->id]),
                    'family_card_url' => route('private.audition.family-card', ['participant' => $p->id]),
                    'additional_doc_url' => $p->additional_doc_path ? route('private.audition.additional-doc', ['participant' => $p->id]) : null,
                    'event' => [
                        'id' => $p->event?->id,
                        'name' => $p->event?->name,
                        'age_category' => $p->event?->age_category,
                        'event_type' => $p->event?->event_type ?? 'audition',
                    ],
                    'session_name' => $p->eventSession?->session_name,
                    'user' => [
                        'name' => $p->user?->name,
                        'email' => $p->user?->email,
                        'phone' => $p->user?->phone,
                    ],
                ];
            });

        $baseCountQuery = AuditionParticipant::query();
        if ($eventId) {
            $baseCountQuery->where('event_id', $eventId);
        }

        return Inertia::render('Admin/Screening/Auditions', [
            'events' => $events,
            'participants' => $participants,
            'filters' => [
                'event_id' => $eventId,
                'status' => $status,
                'search' => $search,
            ],
            'counts' => [
                'total' => (clone $baseCountQuery)->count(),
                'submitted' => (clone $baseCountQuery)->where('screening_status', 'submitted')->count(),
                'passed' => (clone $baseCountQuery)->where('screening_status', 'passed')->count(),
                'rejected' => (clone $baseCountQuery)->where('screening_status', 'rejected')->count(),
            ],
        ]);
    }

    /**
     * Halaman Split-View Workstation pemeriksaan dokumen peserta perorangan untuk suatu event.
     * Dioptimalkan untuk ribuan peserta: antrean dipaginasi ringan (20/halaman) dan
     * dokumen serta audit log hanya dimuat untuk 1 peserta aktif.
     */
    public function auditionEventReview(Request $request, Event $event): Response
    {
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', 'submitted', 'rejected', 'passed', 'draft'], true)) {
            $status = 'all';
        }
        $search = trim((string) $request->query('search', ''));
        $requestedId = $request->integer('participant_id');

        $statusCounts = AuditionParticipant::query()
            ->where('event_id', $event->id)
            ->selectRaw('screening_status, COUNT(*) as aggregate')
            ->groupBy('screening_status')
            ->pluck('aggregate', 'screening_status');

        $counts = [
            'total' => (int) $statusCounts->sum(),
            'submitted' => (int) ($statusCounts->get('submitted') ?? 0),
            'rejected' => (int) ($statusCounts->get('rejected') ?? 0),
            'passed' => (int) ($statusCounts->get('passed') ?? 0),
            'draft' => (int) ($statusCounts->get('draft') ?? 0),
        ];

        $rankSql = "CASE screening_status WHEN 'submitted' THEN 1 WHEN 'rejected' THEN 2 WHEN 'passed' THEN 3 ELSE 4 END";
        $baseQueueQuery = AuditionParticipant::query()->where('event_id', $event->id);

        if ($status !== 'all') {
            $baseQueueQuery->where('screening_status', $status);
        }

        if ($search !== '') {
            $baseQueueQuery->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
                if (ctype_digit($search)) {
                    $q->orWhere('bib_number', (int) $search);
                }
            });
        }

        $activeModel = null;
        if ($requestedId > 0) {
            $activeModel = (clone $baseQueueQuery)
                ->whereKey($requestedId)
                ->with(['eventSession:id,session_name', 'user:id,name,email,phone', 'latestPayment'])
                ->first();
        }

        if (! $activeModel) {
            $activeModel = (clone $baseQueueQuery)
                ->orderByRaw($rankSql)
                ->orderBy('id')
                ->with(['eventSession:id,session_name', 'user:id,name,email,phone', 'latestPayment'])
                ->first();
        }

        $prevId = null;
        $nextId = null;
        $activePosition = 0;
        $defaultPage = 1;

        if ($activeModel) {
            $rank = match ((string) $activeModel->screening_status) {
                'submitted' => 1,
                'rejected' => 2,
                'passed' => 3,
                default => 4,
            };
            $activeId = $activeModel->id;

            $aheadCount = (clone $baseQueueQuery)
                ->whereRaw("({$rankSql} < ? OR ({$rankSql} = ? AND id < ?))", [$rank, $rank, $activeId])
                ->count();

            $activePosition = $aheadCount + 1;
            $defaultPage = intdiv($aheadCount, 20) + 1;

            $prevId = (clone $baseQueueQuery)
                ->whereRaw("({$rankSql} < ? OR ({$rankSql} = ? AND id < ?))", [$rank, $rank, $activeId])
                ->orderByRaw("{$rankSql} DESC")
                ->orderByDesc('id')
                ->value('id');

            $nextId = (clone $baseQueueQuery)
                ->whereRaw("({$rankSql} > ? OR ({$rankSql} = ? AND id > ?))", [$rank, $rank, $activeId])
                ->orderByRaw("{$rankSql} ASC")
                ->orderBy('id')
                ->value('id');
        }

        $targetPage = $request->filled('page') ? max(1, $request->integer('page', 1)) : $defaultPage;

        $paginator = (clone $baseQueueQuery)
            ->select([
                'id',
                'event_id',
                'event_session_id',
                'registration_number',
                'bib_number',
                'full_name',
                'nickname',
                'birth_date',
                'preferred_position',
                'screening_status',
                'status',
            ])
            ->orderByRaw($rankSql)
            ->orderBy('id')
            ->paginate(20, ['*'], 'page', $targetPage)
            ->withQueryString();

        $activeLogs = $activeModel
            ? AuditLog::query()
                ->with('actor:id,name')
                ->where('auditable_type', AuditionParticipant::class)
                ->where('auditable_id', $activeModel->id)
                ->orderByDesc('id')
                ->limit(5)
                ->get()
            : collect();

        $activePayload = $activeModel
            ? $this->formatAuditionReviewParticipant($event, $activeModel, $activeLogs)
            : null;

        $queueItems = collect($paginator->items())->map(function (AuditionParticipant $p) use ($event, $activePayload) {
            if ($activePayload && $p->id === $activePayload['id']) {
                return $activePayload;
            }

            return [
                'id' => $p->id,
                'registration_number' => $p->registration_number,
                'bib_number' => $p->bib_number,
                'full_name' => $p->full_name,
                'nickname' => $p->nickname,
                'preferred_position' => $p->preferred_position,
                'screening_status' => $p->screening_status,
                'screening_status_label' => $p->screeningStatusLabel(),
                'is_age_valid' => $event->isBirthDateEligible($p->birth_date?->format('Y-m-d')),
            ];
        })->values()->all();

        return Inertia::render('Admin/Screening/AuditionReview', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'event_type' => $event->event_type ?? 'audition',
                'participant_type' => $event->participant_type ?? 'individual',
                'age_category' => $event->age_category,
                'min_birth_date' => $event->min_birth_date?->format('d M Y'),
                'max_birth_date' => $event->max_birth_date?->format('d M Y'),
                'required_documents' => $event->required_documents ?? [],
            ],
            'activeParticipant' => $activePayload,
            'participants' => $queueItems,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'links' => $paginator->linkCollection()->toArray(),
            ],
            'navigation' => [
                'prev_id' => $prevId,
                'next_id' => $nextId,
                'active_position' => $activePosition,
                'total_filtered' => $paginator->total(),
            ],
            'counts' => $counts,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'participant_id' => $activeModel?->id,
            ],
        ]);
    }

    private function formatAuditionReviewParticipant(Event $event, AuditionParticipant $p, Collection $logs): array
    {
        $pLogs = $logs->take(5)->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'actor_name' => $log->actor?->name ?? 'Sistem',
            'action' => $log->action,
            'reason' => $log->reason ?: (match ($log->action) {
                'audition.screening.passed' => 'Berkas disahkan dan Digital Pass diaktifkan.',
                'audition.screening.unlocked' => 'Kunci screening dibuka untuk pemeriksaan ulang.',
                default => 'Perubahan status verifikasi.',
            }),
            'created_at' => $log->created_at?->format('d M Y, H:i'),
        ])->values();

        return [
            'id' => $p->id,
            'registration_number' => $p->registration_number,
            'bib_number' => $p->bib_number,
            'full_name' => $p->full_name,
            'nickname' => $p->nickname,
            'nik' => $p->nik,
            'birth_date' => $p->birth_date?->format('Y-m-d'),
            'birth_date_formatted' => $p->birth_date?->format('d M Y'),
            'age' => $p->birth_date ? Carbon::parse($p->birth_date)->age : null,
            'is_age_valid' => $event->isBirthDateEligible($p->birth_date?->format('Y-m-d')),
            'gender' => $p->gender,
            'preferred_position' => $p->preferred_position,
            'secondary_position' => $p->secondary_position,
            'dominant_foot' => $p->dominant_foot,
            'height_cm' => $p->height_cm,
            'weight_kg' => $p->weight_kg,
            'current_club_ssb' => $p->current_club_ssb,
            'playing_experience' => $p->playing_experience,
            'jersey_size' => $p->jersey_size,
            'emergency_contact_name' => $p->emergency_contact_name,
            'emergency_contact_phone' => $p->emergency_contact_phone,
            'emergency_contact_relation' => $p->emergency_contact_relation,
            'medical_notes' => $p->medical_notes,
            'is_paid' => $p->isPaid(),
            'payment_status' => $p->latestPayment?->status ?? 'unpaid',
            'invoice_number' => $p->latestPayment?->invoice_number,
            'photo_url' => $p->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
            'screening_status' => $p->screening_status,
            'screening_status_label' => $p->screeningStatusLabel(),
            'is_locked' => $p->screening_status === 'passed',
            'screening_notes' => $p->screening_notes,
            'screening_code' => $p->screening_code,
            'verified_at' => $p->screening_verified_at?->format('d M Y, H:i'),
            'can_download_bib' => $p->canDownloadBib(),
            'session_name' => $p->eventSession?->session_name,
            'user' => [
                'name' => $p->user?->name,
                'email' => $p->user?->email,
                'phone' => $p->user?->phone,
            ],
            'documents' => $p->getStructuredDocuments(),
            'logs' => $pLogs,
        ];
    }

    /**
     * Memperbarui status verifikasi satuan dokumen peserta audisi/clinic secara asynchronous.
     */
    public function verifyAuditionDocument(VerifyAuditionDocumentRequest $request, AuditionParticipant $participant): JsonResponse
    {
        if ($participant->screening_status === 'passed') {
            return response()->json([
                'success' => false,
                'message' => 'Peserta sudah berstatus Sah dan terkunci. Buka kunci terlebih dahulu untuk mengubah status dokumen.',
            ], 422);
        }

        $validated = $request->validated();
        $docType = $validated['document_type'];
        $statuses = $participant->document_statuses ?? [];
        $statuses[$docType] = [
            'validation_status' => $validated['validation_status'],
            'notes' => $validated['validation_status'] === 'invalid' ? ($validated['notes'] ?? null) : null,
        ];

        $participant->update([
            'document_statuses' => $statuses,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status dokumen peserta berhasil diperbarui.',
            'document_type' => $docType,
            'document_statuses' => $statuses,
        ]);
    }

    /**
     * Keputusan verifikasi berkas peserta perorangan (Lolos / Tolak Revisi).
     */
    public function verifyAuditionParticipant(VerifyAuditionParticipantRequest $request, AuditionParticipant $participant): RedirectResponse
    {
        $validated = $request->validated();
        $status = $validated['status'];
        $notes = $validated['notes'] ?? null;
        $user = $request->user();

        if ($status === 'passed') {
            $bib = $participant->bib_number;
            if (! $bib) {
                $maxBib = AuditionParticipant::query()
                    ->where('event_id', $participant->event_id)
                    ->max('bib_number') ?? 0;
                $bib = $maxBib + 1;
            }

            $screeningCode = $participant->screening_code ?: ('SCR-AUD-' . strtoupper(Str::random(10)));

            $docStatuses = $participant->document_statuses ?? [];
            foreach (['birth_certificate' => 'birth_certificate_path', 'family_card' => 'family_card_path', 'additional_doc' => 'additional_doc_path'] as $key => $col) {
                if ($participant->{$col}) {
                    $docStatuses[$key] = ['validation_status' => 'valid', 'notes' => null];
                }
            }

            $participant->update([
                'screening_status' => 'passed',
                'bib_number' => $bib,
                'screening_code' => $screeningCode,
                'screening_notes' => null,
                'document_statuses' => $docStatuses,
                'screening_verified_by' => $user->id,
                'screening_verified_at' => now(),
            ]);

            AuditLog::record(
                entity: $participant,
                action: 'audition.screening.passed',
                actor: $user,
                metadata: [
                    'bib_number' => $bib,
                    'screening_code' => $screeningCode,
                ]
            );

            return redirect()->back()->with('success', "Peserta {$participant->full_name} dinyatakan LOLOS SCREENING! Digital Pass (E-Tiket) resmi aktif (Rompi #{$bib}).");
        }

        // Status rejected
        $participant->update([
            'screening_status' => 'rejected',
            'screening_notes' => trim($notes),
            'screening_verified_by' => $user->id,
            'screening_verified_at' => now(),
        ]);

        AuditLog::record(
            entity: $participant,
            action: 'audition.screening.rejected',
            actor: $user,
            reason: trim($notes),
            metadata: [
                'participant_name' => $participant->full_name,
            ]
        );

        return redirect()->back()->with('success', "Peserta {$participant->full_name} berstatus REVISI BERKAS dengan catatan panitia.");
    }

    /**
     * Membuka kembali status lolos screening peserta perorangan (Emergency Unlock).
     */
    public function unlockAuditionParticipant(UnlockAuditionParticipantRequest $request, AuditionParticipant $participant): RedirectResponse
    {
        $user = $request->user();
        $reason = trim((string) ($request->validated()['reason'] ?? 'Pembukaan kunci darurat oleh admin untuk verifikasi ulang.'));

        $participant->update([
            'screening_status' => 'submitted',
            'screening_notes' => null,
        ]);

        AuditLog::record(
            entity: $participant,
            action: 'audition.screening.unlocked',
            actor: $user,
            reason: $reason ?: 'Pembukaan kunci darurat oleh admin.',
            metadata: [
                'participant_name' => $participant->full_name,
            ]
        );

        return redirect()->back()->with('success', "Kunci screening {$participant->full_name} berhasil dibuka untuk pemeriksaan ulang.");
    }
}
