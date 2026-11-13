<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventRequest;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Models\AuditionParticipant;
use App\Models\Event;
use App\Models\Team;
use App\Services\ImageOptimizationService;
use App\Services\QuotaExpirationService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(
        protected ImageOptimizationService $imageOptimization
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Event::class);

        $events = Event::query()
            ->withOccupiedSlotsCount()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'city' => $event->city,
                'venue_name' => $event->venue_name,
                'venue_maps_url' => $event->venue_maps_url,
                'format' => $event->format,
                'format_label' => Event::FORMATS[$event->format] ?? $event->format,
                'event_type' => $event->event_type ?? 'tournament',
                'event_type_label' => Event::EVENT_TYPES[$event->event_type ?? 'tournament'] ?? ($event->event_type ?? 'Turnamen'),
                'participant_type' => $event->participant_type ?? 'team',
                'quota_type' => $event->quota_type ?? 'fixed',
                'max_participants' => $event->max_participants,
                'age_category' => $event->age_category,
                'max_teams' => $event->max_teams,
                'occupied_slots' => $event->occupiedSlotsCount(),
                'active_teams' => (int) ($event->active_teams_count ?? 0),
                'registration_fee' => (float) $event->registration_fee,
                'payment_deadline_hours' => $event->payment_deadline_hours,
                'match_start_date' => $event->match_start_date?->toDateString(),
                'status' => $event->status,
            ]);

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
        ]);
    }

    /**
     * Halaman Detail Manajemen Event (Event Workspace Hub).
     */
    public function show(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);

        $participantType = $event->participant_type ?? 'team';
        $eventType = $event->event_type ?? 'tournament';

        $eventData = [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'city' => $event->city,
            'venue_name' => $event->venue_name,
            'venue_maps_url' => $event->venue_maps_url,
            'format' => $event->format,
            'format_label' => Event::FORMATS[$event->format] ?? $event->format,
            'event_type' => $eventType,
            'event_type_label' => Event::EVENT_TYPES[$eventType] ?? $eventType,
            'participant_type' => $participantType,
            'quota_type' => $event->quota_type ?? 'fixed',
            'max_participants' => $event->max_participants,
            'max_teams' => $event->max_teams,
            'position_quota_caps' => $event->position_quota_caps ?? [],
            'age_category' => $event->age_category,
            'min_birth_date' => $event->min_birth_date?->format('d M Y'),
            'max_birth_date' => $event->max_birth_date?->format('d M Y'),
            'occupied_slots' => $event->occupiedSlotsCount(),
            'registration_fee' => (float) $event->registration_fee,
            'payment_deadline_hours' => $event->payment_deadline_hours,
            'match_start_date' => $event->match_start_date?->toDateString(),
            'match_end_date' => $event->match_end_date?->toDateString(),
            'status' => $event->status,
            'certificates_released' => (bool) $event->certificates_released,
        ];

        $summary = [];
        $participants = [];
        $participantsPagination = null;
        $participantFilters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', 'all'),
        ];
        $teams = [];
        $sessions = [];
        $stages = [];
        $positionBreakdown = [];

        if ($participantType === 'individual') {
            $baseParticipantsQuery = AuditionParticipant::query()
                ->where('event_id', $event->id);

            $statusCounts = (clone $baseParticipantsQuery)
                ->selectRaw('screening_status, COUNT(*) as aggregate')
                ->groupBy('screening_status')
                ->pluck('aggregate', 'screening_status');

            $totalCount = (int) $statusCounts->sum();
            $submittedCount = (int) ($statusCounts->get('submitted') ?? 0);
            $passedCount = (int) ($statusCounts->get('passed') ?? 0);
            $rejectedCount = (int) ($statusCounts->get('rejected') ?? 0);

            $paidCount = (clone $baseParticipantsQuery)
                ->whereHas('latestPayment', fn ($q) => $q->where('status', 'paid'))
                ->count();

            $digitalPassQuery = (clone $baseParticipantsQuery)
                ->where('screening_status', 'passed')
                ->whereHas('latestPayment', fn ($q) => $q->where('status', 'paid'));

            if ($eventType === 'audition') {
                $digitalPassQuery->whereNotNull('bib_number');
            }

            $summary = [
                'total' => $totalCount,
                'paid' => $paidCount,
                'submitted' => $submittedCount,
                'passed' => $passedCount,
                'rejected' => $rejectedCount,
                'digital_pass_active' => $digitalPassQuery->count(),
            ];

            if (! empty($event->position_quota_caps) && is_array($event->position_quota_caps)) {
                $countsByPos = (clone $baseParticipantsQuery)
                    ->whereNotNull('preferred_position')
                    ->selectRaw('UPPER(preferred_position) as pos, COUNT(*) as aggregate')
                    ->groupByRaw('UPPER(preferred_position)')
                    ->pluck('aggregate', 'pos');

                foreach ($event->position_quota_caps as $pos => $cap) {
                    $posKey = strtoupper((string) $pos);
                    $positionBreakdown[] = [
                        'position' => $posKey,
                        'cap' => (int) $cap,
                        'occupied' => (int) ($countsByPos->get($posKey) ?? 0),
                    ];
                }
            }

            $sessions = $event->eventSessions()
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'session_name' => $s->session_name,
                    'session_date' => $s->session_date?->format('d M Y'),
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                    'max_quota' => $s->max_quota,
                    'occupied_slots' => $s->occupied_slots,
                ])
                ->values();

            $stages = $event->auditionStages()
                ->withCount(['attendances as present_count' => fn ($q) => $q->where('status', 'present')])
                ->orderBy('stage_number')
                ->get()
                ->map(fn ($st) => [
                    'id' => $st->id,
                    'stage_number' => $st->stage_number,
                    'stage_name' => $st->stage_name,
                    'stage_date' => $st->stage_date?->format('d M Y'),
                    'target_quota' => $st->target_quota,
                    'present_count' => (int) ($st->present_count ?? 0),
                    'is_active' => (bool) $st->is_active,
                ])
                ->values();

            $listQuery = (clone $baseParticipantsQuery)
                ->with(['eventSession:id,session_name', 'latestPayment']);

            if (in_array($participantFilters['status'], ['submitted', 'passed', 'rejected', 'draft'], true)) {
                $listQuery->where('screening_status', $participantFilters['status']);
            }

            if ($participantFilters['search'] !== '') {
                $searchTerm = $participantFilters['search'];
                $listQuery->where(function ($q) use ($searchTerm) {
                    $q->where('full_name', 'like', "%{$searchTerm}%")
                        ->orWhere('registration_number', 'like', "%{$searchTerm}%")
                        ->orWhere('nik', 'like', "%{$searchTerm}%")
                        ->orWhere('current_club_ssb', 'like', "%{$searchTerm}%");
                });
            }

            $paginator = $listQuery
                ->orderByRaw("CASE screening_status WHEN 'submitted' THEN 1 WHEN 'rejected' THEN 2 WHEN 'passed' THEN 3 ELSE 4 END")
                ->latest('id')
                ->paginate(25)
                ->withQueryString();

            $participants = collect($paginator->items())->map(function (AuditionParticipant $p) use ($event) {
                $p->setRelation('event', $event);
                $docs = $p->getStructuredDocuments();

                return [
                    'id' => $p->id,
                    'registration_number' => $p->registration_number,
                    'bib_number' => $p->bib_number,
                    'full_name' => $p->full_name,
                    'nickname' => $p->nickname,
                    'nik' => $p->nik,
                    'birth_date' => $p->birth_date?->format('d/m/Y'),
                    'preferred_position' => $p->preferred_position,
                    'current_club_ssb' => $p->current_club_ssb,
                    'session_name' => $p->eventSession?->session_name,
                    'is_paid' => $p->isPaid(),
                    'screening_status' => $p->screening_status,
                    'screening_status_label' => $p->screeningStatusLabel(),
                    'stage_status' => $p->stage_status,
                    'stage_status_label' => $p->stageStatusLabel(),
                    'can_download_bib' => $p->canDownloadBib(),
                    'docs_total' => count($docs),
                    'docs_valid' => collect($docs)->where('validation_status', 'valid')->count(),
                    'photo_url' => $p->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
                ];
            })->values()->all();

            $participantsPagination = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'links' => $paginator->linkCollection()->toArray(),
            ];
        } else {
            $allTeams = Team::query()
                ->where('event_id', $event->id)
                ->with(['latestPayment'])
                ->withCount([
                    'players',
                    'players as passed_players_count' => fn ($q) => $q->where('screening_status', 'passed'),
                    'players as submitted_players_count' => fn ($q) => $q->where('screening_status', 'submitted'),
                    'players as rejected_players_count' => fn ($q) => $q->where('screening_status', 'rejected'),
                ])
                ->latest('id')
                ->get();

            $summary = [
                'total' => $allTeams->count(),
                'paid' => $allTeams->whereIn('status', ['active', 'locked_roster'])->count(),
                'all_passed' => $allTeams->where('screening_status', 'all_passed')->count(),
                'locked_roster' => $allTeams->where('status', 'locked_roster')->count(),
                'groups_count' => $event->groups()->count(),
                'matches_count' => $event->matches()->count(),
                'completed_matches_count' => $event->matches()->where('status', 'completed')->count(),
            ];

            $teams = $allTeams->map(fn (Team $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'origin_association' => $t->origin_association,
                'manager_name' => $t->manager_name,
                'manager_whatsapp' => $t->manager_whatsapp,
                'status' => $t->status,
                'screening_status' => $t->screening_status,
                'players_count' => (int) ($t->players_count ?? 0),
                'passed_players_count' => (int) ($t->passed_players_count ?? 0),
                'submitted_players_count' => (int) ($t->submitted_players_count ?? 0),
                'rejected_players_count' => (int) ($t->rejected_players_count ?? 0),
                'is_paid' => $t->latestPayment?->status === 'paid' || in_array($t->status, ['active', 'locked_roster'], true),
                'logo_url' => $t->logo_path ? Storage::disk('public')->url($t->logo_path) : null,
            ])->values();
        }

        return Inertia::render('Admin/Events/Show', [
            'event' => $eventData,
            'summary' => $summary,
            'positionBreakdown' => $positionBreakdown,
            'sessions' => $sessions,
            'stages' => $stages,
            'participants' => $participants,
            'participantsPagination' => $participantsPagination,
            'participantFilters' => $participantFilters,
            'teams' => $teams,
        ]);
    }

    public function resyncSlots(QuotaExpirationService $quotaExpiration): RedirectResponse
    {
        $this->authorize('viewAny', Event::class);

        $expiredCount = $quotaExpiration->sweepAllEvents();

        Event::query()->get()->each(fn (Event $event) => $event->syncQuotaStatus());

        if ($expiredCount > 0) {
            return back()->with('success', "Sinkronisasi selesai. Berhasil melepas {$expiredCount} slot kedaluwarsa & memperbarui status kuota event.");
        }

        return back()->with('info', 'Sinkronisasi selesai. Seluruh slot tim masih aktif atau telah lunas.');
    }

    public function resyncEventSlots(Event $event, QuotaExpirationService $quotaExpiration): RedirectResponse
    {
        $this->authorize('view', $event);

        $expiredCount = DB::transaction(function () use ($event, $quotaExpiration) {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->first();

            return $lockedEvent ? $quotaExpiration->sweepExpiredForEvent($lockedEvent) : 0;
        });

        if ($expiredCount > 0) {
            return back()->with('success', "Sinkronisasi berhasil: {$expiredCount} slot kedaluwarsa pada event {$event->name} telah dilepas.");
        }

        return back()->with('info', "Kapasitas slot event {$event->name} sudah sesuai. Tidak ada pendaftaran kedaluwarsa.");
    }

    public function create(\Illuminate\Http\Request $request): Response
    {
        $this->authorize('create', Event::class);

        $initialParticipantType = $request->query('participant_type');
        if (! in_array($initialParticipantType, ['team', 'individual'], true)) {
            $initialParticipantType = 'team';
        }

        return Inertia::render('Admin/Events/Form', [
            'event' => null,
            'initialParticipantType' => $initialParticipantType,
            'formats' => Event::FORMATS,
            'matchFormats' => Event::MATCH_FORMATS,
            'eventTypes' => Event::EVENT_TYPES,
            'participantTypes' => Event::PARTICIPANT_TYPES,
            'quotaTypes' => Event::QUOTA_TYPES,
            'defaultFormPresets' => Event::DEFAULT_FORM_PRESETS,
            'statuses' => Event::STATUSES,
            'documentTypes' => Event::DOCUMENT_TYPES,
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $this->authorize('create', Event::class);
        $validated = $request->validated();

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 2;
        while (Event::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $banner = $request->hasFile('banner_file')
            ? $this->imageOptimization->storeWithOptimizedVariant($request->file('banner_file'), 'events/banners', 'public', 1600, 85)
            : null;

        $pdfPath = $request->hasFile('regulation_pdf')
            ? $request->file('regulation_pdf')->store('events/regulations', 'public')
            : null;

        $positionCaps = null;
        if (($validated['participant_type'] ?? 'team') === 'individual' && ! empty($validated['position_quota_caps']) && is_array($validated['position_quota_caps'])) {
            $filtered = [];
            foreach ($validated['position_quota_caps'] as $pos => $cap) {
                if ($cap !== null && $cap !== '' && is_numeric($cap) && (int) $cap > 0) {
                    $filtered[strtoupper($pos)] = (int) $cap;
                }
            }
            if (! empty($filtered)) {
                $positionCaps = $filtered;
            }
        }

        $event = Event::create([
            'name' => trim($validated['name']),
            'slug' => $slug,
            'banner_path' => $banner['original_path'] ?? null,
            'banner_optimized_path' => $banner['optimized_path'] ?? null,
            'short_description' => $this->sanitizeDescription($validated['short_description'] ?? null),
            'venue_name' => trim($validated['venue_name']),
            'venue_maps_url' => ! empty($validated['venue_maps_url']) ? trim($validated['venue_maps_url']) : null,
            'city' => trim($validated['city']),
            'event_type' => $validated['event_type'] ?? 'tournament',
            'participant_type' => $validated['participant_type'] ?? 'team',
            'quota_type' => $validated['quota_type'] ?? 'fixed',
            'max_participants' => ! empty($validated['max_participants']) ? (int) $validated['max_participants'] : null,
            'position_quota_caps' => $positionCaps,
            'form_presets' => $validated['form_presets'] ?? Event::DEFAULT_FORM_PRESETS,
            'custom_fields_schema' => $validated['custom_fields_schema'] ?? null,
            'format' => $validated['format'],
            'match_format' => $validated['match_format'] ?? '11_vs_11',
            'starting_players_count' => (int) ($validated['starting_players_count'] ?? (Event::MATCH_FORMATS[$validated['match_format'] ?? '11_vs_11']['starting_count'] ?? 11)),
            'dsp_cutoff_minutes' => (int) ($validated['dsp_cutoff_minutes'] ?? 30),
            'age_category' => trim($validated['age_category']),
            'min_birth_date' => $validated['min_birth_date'] ?? null,
            'max_birth_date' => $validated['max_birth_date'] ?? null,
            'min_players_per_team' => (int) $validated['min_players_per_team'],
            'max_players_per_team' => (int) $validated['max_players_per_team'],
            'min_gk_per_team' => (int) ($validated['min_gk_per_team'] ?? 2),
            'max_officials_per_team' => (int) $validated['max_officials_per_team'],
            'show_coach_license' => (bool) ($validated['show_coach_license'] ?? true),
            'require_coach_license' => (bool) (($validated['show_coach_license'] ?? true) && ($validated['require_coach_license'] ?? false)),
            'required_documents' => array_values($validated['required_documents']),
            'max_teams' => ! empty($validated['max_teams']) ? (int) $validated['max_teams'] : null,
            'registration_fee' => $validated['registration_fee'],
            'prize_info' => $validated['prize_info'] ?? null,
            'lock_roster_until_paid' => (bool) $validated['lock_roster_until_paid'],
            'payment_deadline_hours' => (int) $validated['payment_deadline_hours'],
            'reupload_grace_hours' => (int) $validated['reupload_grace_hours'],
            'registration_open_at' => $validated['registration_open_at'] ?? null,
            'registration_close_at' => $validated['registration_close_at'] ?? null,
            'match_start_date' => $validated['match_start_date'] ?? null,
            'match_end_date' => $validated['match_end_date'] ?? null,
            'regulation_pdf_path' => $pdfPath,
            'contact_person' => $validated['contact_person'] ?? null,
            'contact_whatsapp' => $validated['contact_whatsapp'] ?? null,
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.events.index')
            ->with('success', "Event {$event->name} dengan kuota maksimal {$event->max_teams} tim berhasil dibuat.");
    }

    public function edit(Event $event): Response
    {
        $this->authorize('update', $event);
        $event->loadCount([
            'teams as occupied_slots_count' => fn ($q) => $q->occupyingQuotaAt(now()),
        ]);

        return Inertia::render('Admin/Events/Form', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'short_description' => $event->short_description,
                'venue_name' => $event->venue_name,
                'venue_maps_url' => $event->venue_maps_url,
                'city' => $event->city,
                'event_type' => $event->event_type ?? 'tournament',
                'participant_type' => $event->participant_type ?? 'team',
                'quota_type' => $event->quota_type ?? 'fixed',
                'max_participants' => $event->max_participants,
                'position_quota_caps' => $event->position_quota_caps ?? ['GK' => null, 'CB' => null, 'FB' => null, 'MF' => null, 'FW' => null],
                'form_presets' => $event->form_presets ?? Event::DEFAULT_FORM_PRESETS,
                'custom_fields_schema' => $event->custom_fields_schema ?? [],
                'format' => $event->format,
                'match_format' => $event->match_format ?? '11_vs_11',
                'starting_players_count' => (int) ($event->starting_players_count ?? 11),
                'dsp_cutoff_minutes' => (int) ($event->dsp_cutoff_minutes ?? 30),
                'age_category' => $event->age_category,
                'min_birth_date' => $event->min_birth_date?->toDateString(),
                'max_birth_date' => $event->max_birth_date?->toDateString(),
                'min_players_per_team' => $event->min_players_per_team,
                'max_players_per_team' => $event->max_players_per_team,
                'min_gk_per_team' => (int) ($event->min_gk_per_team ?? 2),
                'max_officials_per_team' => $event->max_officials_per_team,
                'show_coach_license' => (bool) ($event->show_coach_license ?? true),
                'require_coach_license' => (bool) (($event->show_coach_license ?? true) && ($event->require_coach_license ?? false)),
                'required_documents' => $event->required_documents ?? [],
                'max_teams' => $event->max_teams,
                'occupied_slots' => $event->occupiedSlotsCount(),
                'registration_fee' => (float) $event->registration_fee,
                'prize_info' => $event->prize_info,
                'lock_roster_until_paid' => $event->lock_roster_until_paid,
                'payment_deadline_hours' => $event->payment_deadline_hours,
                'reupload_grace_hours' => $event->reupload_grace_hours,
                'registration_open_at' => $event->registration_open_at?->format('Y-m-d\TH:i'),
                'registration_close_at' => $event->registration_close_at?->format('Y-m-d\TH:i'),
                'match_start_date' => $event->match_start_date?->toDateString(),
                'match_end_date' => $event->match_end_date?->toDateString(),
                'contact_person' => $event->contact_person,
                'contact_whatsapp' => $event->contact_whatsapp,
                'status' => $event->status,
                'banner_url' => $event->banner_path ? Storage::disk('public')->url($event->banner_path) : null,
                'regulation_pdf_url' => $event->regulation_pdf_path ? Storage::disk('public')->url($event->regulation_pdf_path) : null,
            ],
            'formats' => Event::FORMATS,
            'matchFormats' => Event::MATCH_FORMATS,
            'eventTypes' => Event::EVENT_TYPES,
            'participantTypes' => Event::PARTICIPANT_TYPES,
            'quotaTypes' => Event::QUOTA_TYPES,
            'defaultFormPresets' => Event::DEFAULT_FORM_PRESETS,
            'statuses' => Event::STATUSES,
            'documentTypes' => Event::DOCUMENT_TYPES,
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $validated = $request->validated();

        DB::transaction(function () use ($event, $validated, $request) {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $occupied = $lockedEvent->teams()->occupyingQuotaAt(now())->count();

            $quotaType = $validated['quota_type'] ?? $lockedEvent->quota_type ?? 'fixed';
            if ($quotaType === 'fixed' && ! empty($validated['max_teams']) && (int) $validated['max_teams'] < $occupied) {
                throw ValidationException::withMessages([
                    'max_teams' => "Kuota maksimal tidak boleh dikurangi di bawah jumlah tim yang saat ini sedang memegang slot ({$occupied} tim).",
                ]);
            }

            $participantType = $validated['participant_type'] ?? $lockedEvent->participant_type ?? 'team';
            $positionCaps = null;
            if ($participantType === 'individual') {
                if (array_key_exists('position_quota_caps', $validated) && is_array($validated['position_quota_caps'])) {
                    $filtered = [];
                    foreach ($validated['position_quota_caps'] as $pos => $cap) {
                        if ($cap !== null && $cap !== '' && is_numeric($cap) && (int) $cap > 0) {
                            $filtered[strtoupper($pos)] = (int) $cap;
                        }
                    }
                    if (! empty($filtered)) {
                        $positionCaps = $filtered;
                    }
                } else {
                    $positionCaps = $lockedEvent->position_quota_caps;
                }
            }

            $updates = [
                'name' => trim($validated['name']),
                'short_description' => $this->sanitizeDescription($validated['short_description'] ?? null),
                'venue_name' => trim($validated['venue_name']),
                'venue_maps_url' => ! empty($validated['venue_maps_url']) ? trim($validated['venue_maps_url']) : null,
                'city' => trim($validated['city']),
                'event_type' => $validated['event_type'] ?? $lockedEvent->event_type ?? 'tournament',
                'participant_type' => $participantType,
                'quota_type' => $validated['quota_type'] ?? $lockedEvent->quota_type ?? 'fixed',
                'max_participants' => ! empty($validated['max_participants']) ? (int) $validated['max_participants'] : null,
                'position_quota_caps' => $positionCaps,
                'form_presets' => $validated['form_presets'] ?? $lockedEvent->form_presets ?? Event::DEFAULT_FORM_PRESETS,
                'custom_fields_schema' => $validated['custom_fields_schema'] ?? $lockedEvent->custom_fields_schema,
                'format' => $validated['format'],
                'match_format' => $validated['match_format'] ?? $lockedEvent->match_format ?? '11_vs_11',
                'starting_players_count' => (int) ($validated['starting_players_count'] ?? $lockedEvent->starting_players_count ?? (Event::MATCH_FORMATS[$validated['match_format'] ?? '11_vs_11']['starting_count'] ?? 11)),
                'dsp_cutoff_minutes' => (int) ($validated['dsp_cutoff_minutes'] ?? $lockedEvent->dsp_cutoff_minutes ?? 30),
                'age_category' => trim($validated['age_category']),
                'min_birth_date' => $validated['min_birth_date'] ?? null,
                'max_birth_date' => $validated['max_birth_date'] ?? null,
                'min_players_per_team' => (int) ($validated['min_players_per_team'] ?? $lockedEvent->min_players_per_team ?? 1),
                'max_players_per_team' => (int) ($validated['max_players_per_team'] ?? $lockedEvent->max_players_per_team ?? 1),
                'min_gk_per_team' => (int) ($validated['min_gk_per_team'] ?? $lockedEvent->min_gk_per_team ?? 1),
                'max_officials_per_team' => (int) ($validated['max_officials_per_team'] ?? $lockedEvent->max_officials_per_team ?? 1),
                'show_coach_license' => (bool) ($validated['show_coach_license'] ?? $lockedEvent->show_coach_license ?? true),
                'require_coach_license' => (bool) (($validated['show_coach_license'] ?? $lockedEvent->show_coach_license ?? true) && ($validated['require_coach_license'] ?? $lockedEvent->require_coach_license ?? false)),
                'required_documents' => array_values($validated['required_documents'] ?? []),
                'max_teams' => ! empty($validated['max_teams']) ? (int) $validated['max_teams'] : null,
                'registration_fee' => $validated['registration_fee'] ?? 0,
                'prize_info' => $validated['prize_info'] ?? null,
                'lock_roster_until_paid' => (bool) ($validated['lock_roster_until_paid'] ?? true),
                'payment_deadline_hours' => (int) $validated['payment_deadline_hours'],
                'reupload_grace_hours' => (int) $validated['reupload_grace_hours'],
                'registration_open_at' => $validated['registration_open_at'] ?? null,
                'registration_close_at' => $validated['registration_close_at'] ?? null,
                'match_start_date' => $validated['match_start_date'] ?? null,
                'match_end_date' => $validated['match_end_date'] ?? null,
                'contact_person' => $validated['contact_person'] ?? null,
                'contact_whatsapp' => $validated['contact_whatsapp'] ?? null,
                'status' => $validated['status'],
            ];

            if ($request->hasFile('banner_file')) {
                $newBanner = $this->imageOptimization->storeWithOptimizedVariant($request->file('banner_file'), 'events/banners', 'public', 1600, 85);
                if ($lockedEvent->banner_path) {
                    Storage::disk('public')->delete($lockedEvent->banner_path);
                }
                if ($lockedEvent->banner_optimized_path && $lockedEvent->banner_optimized_path !== $lockedEvent->banner_path) {
                    Storage::disk('public')->delete($lockedEvent->banner_optimized_path);
                }
                $updates['banner_path'] = $newBanner['original_path'];
                $updates['banner_optimized_path'] = $newBanner['optimized_path'];
            }

            if ($request->hasFile('regulation_pdf')) {
                $newPdf = $request->file('regulation_pdf')->store('events/regulations', 'public');
                if ($lockedEvent->regulation_pdf_path) {
                    Storage::disk('public')->delete($lockedEvent->regulation_pdf_path);
                }
                $updates['regulation_pdf_path'] = $newPdf;
            }

            $lockedEvent->update($updates);
            $lockedEvent->syncQuotaStatus($occupied);
        });

        return redirect()->route('admin.events.index')
            ->with('success', 'Pengaturan kompetisi dan kuota maksimal tim berhasil diperbarui.');
    }

    protected function sanitizeDescription(?string $html): ?string
    {
        return HtmlSanitizer::clean($html);
    }
}
