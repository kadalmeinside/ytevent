<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\RegisterTeamRequest;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Services\TeamRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicEventController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Event::query()
            ->where('status', '!=', 'draft')
            ->withOccupiedSlotsCount();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('venue_name', 'like', "%{$search}%");
            });
        }

        if ($format = $request->query('format')) {
            $query->where('format', $format);
        }

        if ($ageCategory = $request->query('age_category')) {
            $query->where('age_category', $ageCategory);
        }

        if ($status = $request->query('status')) {
            if ($status === 'open') {
                $query->where('status', 'open');
            } elseif ($status === 'quota_full') {
                $query->where('status', 'quota_full');
            } else {
                $query->where('status', $status);
            }
        }

        $events = $query
            ->orderByRaw("CASE WHEN status = 'open' THEN 1 WHEN status = 'quota_full' THEN 2 WHEN status = 'ongoing' THEN 3 ELSE 4 END")
            ->orderBy('match_start_date')
            ->get()
            ->map(fn (Event $event) => $this->transformPublicEvent($event));

        $ageCategories = Event::query()
            ->where('status', '!=', 'draft')
            ->select('age_category')
            ->distinct()
            ->orderBy('age_category')
            ->pluck('age_category')
            ->values();

        return Inertia::render('Public/Home', [
            'events' => $events,
            'filters' => [
                'search' => (string) $request->query('search', ''),
                'format' => (string) $request->query('format', ''),
                'age_category' => (string) $request->query('age_category', ''),
                'status' => (string) $request->query('status', ''),
            ],
            'formats' => Event::FORMATS,
            'ageCategories' => $ageCategories,
            'stats' => [
                'total_events' => Event::query()->where('status', '!=', 'draft')->count(),
                'total_teams' => Team::query()->occupyingQuotaAt(now())->count(),
                'total_verified_players' => Player::query()->where('screening_status', 'passed')->count(),
            ],
        ]);
    }

    public function show(Event $event): Response
    {
        if ($event->status === 'draft') {
            abort(404);
        }

        $event->loadCount([
            'teams as occupied_slots_count' => fn ($q) => $q->occupyingQuotaAt(now()),
            'teams as active_teams_count' => fn ($q) => $q->whereIn('status', ['active', 'locked_roster']),
        ]);

        $registeredTeams = $event->teams()
            ->whereIn('status', ['active', 'locked_roster'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'origin_association' => $team->origin_association,
                'status' => $team->status,
                'logo_url' => $team->logo_path ? Storage::disk('public')->url($team->logo_path) : null,
            ]);

        return Inertia::render('Public/EventDetail', [
            'event' => $this->transformPublicEvent($event, detailed: true),
            'registeredTeams' => $registeredTeams,
        ]);
    }

    public function showRegister(Request $request, Event $event): Response|RedirectResponse
    {
        if ($event->status === 'draft') {
            abort(404);
        }

        $event->loadCount([
            'teams as occupied_slots_count' => fn ($q) => $q->occupyingQuotaAt(now()),
        ]);

        $user = $request->user();

        return Inertia::render('Public/RegisterTeam', [
            'event' => $this->transformPublicEvent($event, detailed: true),
            'prefillUser' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ] : null,
        ]);
    }

    public function register(
        RegisterTeamRequest $request,
        Event $event,
        TeamRegistrationService $registrationService
    ): RedirectResponse {
        if ($event->status === 'draft') {
            abort(404);
        }

        $team = $registrationService->registerNewTeam(
            event: $event,
            validated: $request->validated(),
            logoFile: $request->file('logo'),
            authenticatedUser: $request->user()
        );

        if (! $request->user()) {
            Auth::login($team->manager);
            $request->session()->regenerate();
        }

        $request->session()->put('active_manager_team_id', $team->id);

        return redirect()->route('manager.payments')
            ->with('success', "Pendaftaran tim {$team->name} berhasil diamankan! Silakan selesaikan pembayaran sebelum batas waktu reservasi berakhir.");
    }

    protected function transformPublicEvent(Event $event, bool $detailed = false): array
    {
        $occupied = $event->occupiedSlotsCount();
        $available = max(0, $event->max_teams - $occupied);
        $effectiveStatus = ($event->status === 'open' && $available === 0)
            ? 'quota_full'
            : (($event->status === 'quota_full' && $available > 0 && $event->isWithinRegistrationWindow()) ? 'open' : $event->status);

        $data = [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'short_description' => $event->short_description,
            'venue_name' => $event->venue_name,
            'venue_maps_url' => $event->venue_maps_url,
            'city' => $event->city,
            'format' => $event->format,
            'format_label' => Event::FORMATS[$event->format] ?? $event->format,
            'age_category' => $event->age_category,
            'min_birth_date' => $event->min_birth_date?->toDateString(),
            'max_birth_date' => $event->max_birth_date?->toDateString(),
            'max_teams' => $event->max_teams,
            'occupied_slots' => $occupied,
            'available_slots' => $available,
            'registration_fee' => (float) $event->registration_fee,
            'prize_info' => $event->prize_info,
            'payment_deadline_hours' => $event->payment_deadline_hours,
            'registration_open_at' => $event->registration_open_at?->toIso8601String(),
            'registration_close_at' => $event->registration_close_at?->toIso8601String(),
            'match_start_date' => $event->match_start_date?->toDateString(),
            'match_end_date' => $event->match_end_date?->toDateString(),
            'status' => $effectiveStatus,
            'can_register' => $event->canAcceptRegistration(),
            'banner_url' => $event->banner_path ? Storage::disk('public')->url($event->banner_path) : null,
            'regulation_pdf_url' => $event->regulation_pdf_path ? Storage::disk('public')->url($event->regulation_pdf_path) : null,
        ];

        if ($detailed) {
            $data['min_players_per_team'] = $event->min_players_per_team;
            $data['max_players_per_team'] = $event->max_players_per_team;
            $data['max_officials_per_team'] = $event->max_officials_per_team;
            $data['lock_roster_until_paid'] = $event->lock_roster_until_paid;
            $data['contact_person'] = $event->contact_person;
            $data['contact_whatsapp'] = $event->contact_whatsapp;
            $data['required_documents'] = collect($event->required_documents ?? [])
                ->map(fn (string $type) => [
                    'type' => $type,
                    'label' => Event::documentLabel($type),
                ])
                ->values();
        }

        return $data;
    }
}

