<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\RegisterTeamRequest;
use App\Models\Event;
use App\Models\Player;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Services\StandingsCalculatorService;
use App\Services\TeamRegistrationService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

        $event->load([
            'groups.groupTeams.team',
            'matches.homeTeam',
            'matches.awayTeam',
            'matches.group',
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
                'home_jersey_color' => $team->home_jersey_color,
                'logo_url' => $this->publicStorageUrl($team->logo_optimized_path ?: $team->logo_path),
            ]);

        $groups = $event->groups->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'standings' => $g->groupTeams->map(fn ($gt) => [
                'team_id' => $gt->team_id,
                'team_name' => $gt->team?->name ?? '-',
                'played' => $gt->played,
                'won' => $gt->won,
                'drawn' => $gt->drawn,
                'lost' => $gt->lost,
                'goals_for' => $gt->goals_for,
                'goals_against' => $gt->goals_against,
                'goal_difference' => $gt->goal_difference,
                'points' => $gt->points,
            ])->values(),
        ])->values();

        $matches = $event->matches->map(fn ($m) => [
            'id' => $m->id,
            'match_number' => $m->match_number,
            'stage' => $m->stage,
            'stage_label' => TournamentMatch::stageLabel($m->stage),
            'group_name' => $m->group?->name,
            'home_team_name' => $m->homeTeam?->name ?? 'TBD',
            'away_team_name' => $m->awayTeam?->name ?? 'TBD',
            'kickoff_formatted' => $m->kickoff_at?->translatedFormat('d M Y, H:i').($m->kickoff_at ? ' WIB' : ''),
            'pitch_name' => $m->pitch_name ?: 'Lapangan Utama',
            'status' => $m->status,
            'status_label' => TournamentMatch::STATUSES[$m->status] ?? $m->status,
            'home_score' => $m->home_score,
            'away_score' => $m->away_score,
        ])->values();

        $leaderboard = app(StandingsCalculatorService::class)->getEventLeaderboard($event);

        return Inertia::render('Public/EventDetail', [
            'event' => $this->transformPublicEvent($event, detailed: true),
            'registeredTeams' => $registeredTeams,
            'groups' => $groups,
            'matches' => $matches,
            'leaderboard' => $leaderboard,
        ])->withViewData([
            'seoMeta' => $this->buildEventSeoMeta($event, isRegisterPage: false),
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
        ])->withViewData([
            'seoMeta' => $this->buildEventSeoMeta($event, isRegisterPage: true),
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

        $latestPayment = $team->latestPayment;
        $autoSnap = false;

        if ($latestPayment
            && $latestPayment->status === 'unpaid'
            && \App\Services\PaymentService::activeMode() === 'midtrans'
            && ! config('payment.sandbox_simulator')
        ) {
            try {
                $paymentService = app(\App\Services\PaymentService::class);
                $paymentService->initiateGatewayPayment(
                    payment: $latestPayment,
                    provider: 'midtrans'
                );
                $autoSnap = true;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Auto initiate Midtrans Snap on registration failed: ' . $e->getMessage());
            }
        }

        if ($autoSnap) {
            $request->session()->flash('auto_open_snap', true);
        }

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
            'short_description' => HtmlSanitizer::clean($event->short_description),
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
            'banner_url' => $this->publicStorageUrl($event->banner_optimized_path ?: $event->banner_path),
            'regulation_pdf_url' => $this->publicStorageUrl($event->regulation_pdf_path),
        ];

        if ($detailed) {
            $data['min_players_per_team'] = $event->min_players_per_team;
            $data['max_players_per_team'] = $event->max_players_per_team;
            $data['min_gk_per_team'] = (int) ($event->min_gk_per_team ?? 2);
            $data['max_officials_per_team'] = $event->max_officials_per_team;
            $data['show_coach_license'] = (bool) ($event->show_coach_license ?? true);
            $data['require_coach_license'] = (bool) (($event->show_coach_license ?? true) && ($event->require_coach_license ?? false));
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

    protected function publicStorageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)) {
            return null;
        }

        $cleanPath = ltrim($path, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            return url('/'.$cleanPath);
        }

        return url('/storage/'.$cleanPath);
    }

    protected function cleanPlainDescription(Event $event, bool $isRegisterPage = false): string
    {
        if ($isRegisterPage) {
            $occupied = $event->occupiedSlotsCount();
            $available = max(0, $event->max_teams - $occupied);

            return "Formulir pendaftaran resmi tim sepak bola untuk kompetisi {$event->name} ({$event->age_category}) di {$event->venue_name}, {$event->city}. Sisa slot tersedia: {$available} dari {$event->max_teams} tim.";
        }

        $raw = (string) ($event->short_description ?? '');
        if ($raw !== '') {
            $decoded = html_entity_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $stripped = preg_replace('/\s+/u', ' ', trim(strip_tags($decoded))) ?? '';
            if ($stripped !== '') {
                return Str::limit($stripped, 160);
            }
        }

        return "Pendaftaran resmi {$event->name} ({$event->age_category}) di {$event->venue_name}, {$event->city}. Kuota maksimal {$event->max_teams} tim.";
    }

    protected function buildEventSeoMeta(Event $event, bool $isRegisterPage = false): array
    {
        $appName = (string) SystemSetting::get('app_name', config('app.name', 'Persija Development'));
        $occupied = $event->occupiedSlotsCount();
        $available = max(0, $event->max_teams - $occupied);
        $bannerUrl = $this->publicStorageUrl($event->banner_optimized_path ?: $event->banner_path);
        $description = $this->cleanPlainDescription($event, $isRegisterPage);
        $detailUrl = route('events.show', ['event' => $event->slug]);
        $registerUrl = route('events.register', ['event' => $event->slug]);

        $ogTitle = $isRegisterPage
            ? "Daftar Tim — {$event->name} ({$event->age_category})"
            : "{$event->name} ({$event->age_category})";

        $pageTitle = "{$ogTitle} - {$appName}";

        $sportsEventSchema = array_filter([
            '@type' => 'SportsEvent',
            '@id' => $detailUrl.'#event',
            'name' => "{$event->name} ({$event->age_category})",
            'description' => $this->cleanPlainDescription($event, false),
            'sport' => 'Soccer',
            'startDate' => $event->match_start_date?->toDateString(),
            'endDate' => ($event->match_end_date ?? $event->match_start_date)?->toDateString(),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'maximumAttendeeCapacity' => (int) $event->max_teams,
            'remainingAttendeeCapacity' => (int) $available,
            'image' => $bannerUrl ? [$bannerUrl] : null,
            'url' => $detailUrl,
            'location' => [
                '@type' => 'Place',
                'name' => $event->venue_name,
                'hasMap' => $event->venue_maps_url ?: null,
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $event->city,
                    'addressCountry' => 'ID',
                ],
            ],
            'offers' => array_filter([
                '@type' => 'Offer',
                'url' => $registerUrl,
                'price' => (float) $event->registration_fee,
                'priceCurrency' => 'IDR',
                'availability' => $event->canAcceptRegistration()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/SoldOut',
                'validFrom' => $event->registration_open_at?->toIso8601String(),
                'validThrough' => $event->registration_close_at?->toIso8601String(),
            ], fn ($v) => $v !== null),
        ], fn ($v) => $v !== null);

        $breadcrumbSchema = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Katalog Kompetisi',
                    'item' => url('/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => "{$event->name} ({$event->age_category})",
                    'item' => $detailUrl,
                ],
            ],
        ];

        return [
            'title' => $pageTitle,
            'og_title' => $ogTitle,
            'description' => $description,
            'image' => $bannerUrl,
            'image_alt' => "{$event->name} ({$event->age_category})",
            'type' => 'article',
            'url' => $isRegisterPage ? $registerUrl : $detailUrl,
            'json_ld' => [$breadcrumbSchema, $sportsEventSchema],
        ];
    }
}
