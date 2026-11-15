<?php

namespace App\Http\Middleware;

use App\Models\AuditionParticipant;
use App\Models\EmailNotificationLog;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\Payment;
use App\Models\Player;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookLog;
use App\Modules\Simulation\SimulationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'auto_open_snap' => fn () => (bool) ($request->session()->get('auto_open_snap', false) || $request->has('snap')),
            ],
            'adminNotifications' => fn () => $user && $user->hasAnyRole(['superadmin', 'finance', 'screening_officer']) ? (function () {
                $pendingPayments = Payment::query()->where('status', 'waiting_verification')->count();
                $refundRequired = Payment::query()->where('status', 'refund_required')->count();
                $pendingScreeningPlayers = Player::query()->where('screening_status', 'submitted')->count();
                $pendingScreeningTeams = Team::query()
                    ->where('status', 'active')
                    ->whereHas('players', fn ($q) => $q->where('screening_status', 'submitted'))
                    ->count();
                $readyToLockTeams = Team::query()
                    ->where('status', 'active')
                    ->where('screening_status', 'all_passed')
                    ->count();
                $lockedTeams = Team::query()
                    ->where('status', 'locked_roster')
                    ->count();
                $failedEmails = EmailNotificationLog::query()
                    ->where('status', 'failed')
                    ->count();
                $failedWebhooks = WebhookLog::query()
                    ->where('status', 'failed')
                    ->count();
                $failedJobs = DB::table('failed_jobs')->count();

                return [
                    'pending_payments_count' => $pendingPayments,
                    'refund_required_count' => $refundRequired,
                    'pending_screening_players_count' => $pendingScreeningPlayers,
                    'pending_screening_teams_count' => $pendingScreeningTeams,
                    'ready_to_lock_teams_count' => $readyToLockTeams,
                    'locked_teams_count' => $lockedTeams,
                    'failed_emails_count' => $failedEmails,
                    'failed_webhooks_count' => $failedWebhooks,
                    'failed_jobs_count' => $failedJobs,
                    'total_actionable' => $pendingPayments + $refundRequired + $pendingScreeningPlayers + $failedEmails + $failedWebhooks + $failedJobs,
                ];
            })() : null,
            'accountNotifications' => fn () => $user && $user->hasAnyRole(['team_manager', 'participant_parent'])
                ? $this->buildAccountNotifications($user)
                : null,
            'systemSettings' => fn () => [
                'app_name' => SystemSetting::get('app_name', config('app.name', 'YTEvent Football')),
                'app_tagline' => SystemSetting::get('app_tagline', 'Multi-Event Competition Portal'),
                'header_logo_style' => SystemSetting::get('header_logo_style', 'logo_only'),
                'app_logo_url' => SystemSetting::get('app_logo') ? Storage::disk('public')->url(SystemSetting::get('app_logo')) : null,
                'app_favicon_url' => SystemSetting::get('app_favicon') ? Storage::disk('public')->url(SystemSetting::get('app_favicon')) : null,
                'footer_copyright' => SystemSetting::get('footer_copyright', 'YTEvent Multi-Event Football Tournament & League Management System'),
                'contact_whatsapp' => SystemSetting::get('contact_whatsapp'),
                'payment_method_mode' => SystemSetting::get('payment_method_mode', 'midtrans'),
                'manual_bank_accounts' => SystemSetting::get('manual_bank_accounts', []),
                'fee_borne_by' => SystemSetting::get('fee_borne_by', 'team'),
                'fee_fixed_amount' => (int) SystemSetting::get('midtrans_va_fee', 4000),
                'simulation_mode_enabled' => SimulationService::isEnabled(),
            ],
            'simulationContext' => fn () => config('simulation.master_enabled', false) ? (function () use ($request) {
                $isEnabled = SimulationService::isEnabled();
                if (! $isEnabled) {
                    return [
                        'enabled' => false,
                        'master_enabled' => true,
                    ];
                }

                $activeTeamId = $request->session()->get('active_manager_team_id');
                $latestTeam = $activeTeamId ? Team::with('event')->find($activeTeamId) : null;
                if (! $latestTeam) {
                    $latestTeam = Team::with('event')->latest('id')->first();
                }

                $openEvents = Event::query()
                    ->select(['id', 'name', 'slug', 'age_category', 'status', 'participant_type', 'event_type'])
                    ->orderByDesc('id')
                    ->limit(12)
                    ->get();

                $recentTeams = Team::query()
                    ->with('event:id,name,age_category')
                    ->select(['id', 'event_id', 'name', 'status', 'screening_status', 'manager_email'])
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get();

                return [
                    'enabled' => true,
                    'master_enabled' => true,
                    'active_team' => $latestTeam ? [
                        'id' => $latestTeam->id,
                        'name' => $latestTeam->name,
                        'status' => $latestTeam->status,
                        'screening_status' => $latestTeam->screening_status,
                        'event_name' => $latestTeam->event?->name,
                    ] : null,
                    'events' => $openEvents,
                    'teams' => $recentTeams,
                ];
            })() : ['enabled' => false, 'master_enabled' => false],
        ];
    }

    /**
     * Susun notifikasi lintas kepesertaan akun (tim turnamen, peserta perorangan, dan pengumuman event aktif).
     *
     * @return array{items: array<int, array<string, mixed>>, unpaid_total_count: int}
     */
    protected function buildAccountNotifications(User $user): array
    {
        $items = collect();
        $unpaidTotalCount = 0;
        $activeEventIds = collect();

        $teams = Team::query()
            ->with(['event', 'latestPayment', 'players'])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        foreach ($teams as $team) {
            $event = $team->event;
            $isCompleted = $event?->status === 'completed';
            $latestPayment = $team->latestPayment;
            $isPaid = $latestPayment?->status === 'paid' || (float) ($event?->registration_fee ?? 0) <= 0;
            $rejectedPlayersCount = $team->players->where('screening_status', 'rejected')->count();

            if ($event && ! $isCompleted) {
                $activeEventIds->push($event->id);
            }

            if (! $isCompleted && (! $isPaid || in_array($team->status, ['pending_payment', 'expired'], true) || $latestPayment?->status === 'rejected')) {
                $unpaidTotalCount++;
                $isRejected = $latestPayment?->status === 'rejected';
                $items->push([
                    'id' => "team-pay-{$team->id}-" . ($latestPayment?->status ?? $team->status ?? 'unpaid'),
                    'category' => 'Tagihan Tim',
                    'variant' => $isRejected ? 'danger' : 'warning',
                    'priority' => $isRejected ? 0 : 1,
                    'title' => $isRejected
                        ? "Bukti Pembayaran Ditolak: {$team->name}"
                        : "Menunggu Pembayaran: {$team->name}",
                    'message' => $isRejected
                        ? ($latestPayment->rejection_reason ?: "Unggah ulang bukti transfer atau bayar ulang untuk {$event?->name}.")
                        : "Selesaikan tagihan pendaftaran agar slot {$event?->name} tetap aktif.",
                    'context_label' => $event?->name ?? 'Turnamen Tim',
                    'time_label' => $latestPayment?->updated_at?->diffForHumans() ?? $team->created_at?->diffForHumans(),
                    'href' => route('manager.payments', ['team_id' => $team->id, 'team_context' => 1]),
                    'action_label' => 'Buka Pembayaran',
                ]);
            } elseif (! $isCompleted && $rejectedPlayersCount > 0) {
                $items->push([
                    'id' => "team-players-rejected-{$team->id}-{$rejectedPlayersCount}",
                    'category' => 'Perlu Revisi',
                    'variant' => 'danger',
                    'priority' => 0,
                    'title' => "{$rejectedPlayersCount} Berkas Pemain Perlu Revisi",
                    'message' => "Tim {$team->name} ({$event?->name}) memiliki berkas pemain yang ditolak panitia.",
                    'context_label' => $team->name,
                    'time_label' => $team->updated_at?->diffForHumans(),
                    'href' => route('manager.players', ['team_id' => $team->id]),
                    'action_label' => 'Perbaiki Berkas',
                ]);
            } elseif (! $isCompleted && $team->status === 'active' && $team->screening_status === 'all_passed') {
                $items->push([
                    'id' => "team-ready-lock-{$team->id}",
                    'category' => 'Aksi Tim',
                    'variant' => 'warning',
                    'priority' => 1,
                    'title' => "Skuad Siap Dikunci: {$team->name}",
                    'message' => "Seluruh pemain sah lolos screening. Kunci skuad final untuk mengunduh ID Card {$event?->name}.",
                    'context_label' => $team->name,
                    'time_label' => $team->updated_at?->diffForHumans(),
                    'href' => route('manager.accreditation.index', ['team_id' => $team->id]),
                    'action_label' => 'Kunci Skuad',
                ]);
            } elseif (! $isCompleted && $team->status === 'locked_roster') {
                $items->push([
                    'id' => "team-locked-{$team->id}",
                    'category' => 'Status Tim',
                    'variant' => 'success',
                    'priority' => 3,
                    'title' => "ID Card & Roster Siap Cetak: {$team->name}",
                    'message' => "Skuad final {$team->name} untuk {$event?->name} telah disahkan dan terkunci.",
                    'context_label' => $event?->name ?? $team->name,
                    'time_label' => $team->roster_locked_at?->diffForHumans() ?? $team->updated_at?->diffForHumans(),
                    'href' => route('manager.accreditation.index', ['team_id' => $team->id]),
                    'action_label' => 'Lihat Akreditasi',
                ]);
            }
        }

        $participants = AuditionParticipant::query()
            ->with(['event', 'latestPayment'])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        foreach ($participants as $participant) {
            $event = $participant->event;
            $isCompleted = $event?->status === 'completed';
            $isPaid = $participant->isPaid();
            $latestPayment = $participant->latestPayment;

            if ($event && ! $isCompleted) {
                $activeEventIds->push($event->id);
            }

            if (! $isCompleted && ! $isPaid) {
                $unpaidTotalCount++;
                $isRejected = $latestPayment?->status === 'rejected';
                $items->push([
                    'id' => "aud-pay-{$participant->id}-" . ($latestPayment?->status ?? 'unpaid'),
                    'category' => 'Tagihan Peserta',
                    'variant' => $isRejected ? 'danger' : 'warning',
                    'priority' => $isRejected ? 0 : 1,
                    'title' => "Tagihan Belum Lunas: {$participant->full_name}",
                    'message' => "Selesaikan pembayaran pendaftaran untuk {$event?->name}.",
                    'context_label' => $event?->name ?? 'Seleksi Perorangan',
                    'time_label' => $latestPayment?->updated_at?->diffForHumans() ?? $participant->created_at?->diffForHumans(),
                    'href' => route('manager.payments', ['participant_id' => $participant->id]),
                    'action_label' => 'Bayar Tagihan',
                ]);
            } elseif (! $isCompleted && $participant->screening_status === 'rejected') {
                $items->push([
                    'id' => "aud-rejected-{$participant->id}",
                    'category' => 'Perlu Revisi',
                    'variant' => 'danger',
                    'priority' => 0,
                    'title' => "Berkas Peserta Perlu Revisi: {$participant->full_name}",
                    'message' => $participant->screening_notes ?: "Periksa catatan perbaikan berkas pada {$event?->name}.",
                    'context_label' => $participant->full_name,
                    'time_label' => $participant->updated_at?->diffForHumans(),
                    'href' => route('manager.dashboard', ['view' => 'audition', 'participant_id' => $participant->id]),
                    'action_label' => 'Lihat Catatan',
                ]);
            } elseif ($isPaid && $participant->screening_status === 'passed') {
                if ($isCompleted && $event?->certificates_released) {
                    $items->push([
                        'id' => "aud-cert-{$participant->id}",
                        'category' => 'Piagam Terbit',
                        'variant' => 'success',
                        'priority' => 2,
                        'title' => "Piagam & Sertifikat Tersedia: {$participant->full_name}",
                        'message' => "Status akhir: {$participant->stageStatusLabel()} pada {$event?->name}.",
                        'context_label' => $event?->name ?? $participant->full_name,
                        'time_label' => $participant->updated_at?->diffForHumans(),
                        'href' => route('manager.dashboard', ['view' => 'audition', 'participant_id' => $participant->id]),
                        'action_label' => 'Buka Piagam',
                    ]);
                } elseif (! $isCompleted) {
                    $bibLabel = $participant->bib_number ? " (Bib #{$participant->bib_number})" : '';
                    $items->push([
                        'id' => "aud-pass-{$participant->id}-{$participant->stage_status}",
                        'category' => 'Status Seleksi',
                        'variant' => 'info',
                        'priority' => 2,
                        'title' => "Digital Pass Aktif: {$participant->full_name}{$bibLabel}",
                        'message' => "Status seleksi: {$participant->stageStatusLabel()} pada {$event?->name}.",
                        'context_label' => $event?->name ?? $participant->full_name,
                        'time_label' => $participant->screening_verified_at?->diffForHumans() ?? $participant->updated_at?->diffForHumans(),
                        'href' => route('manager.dashboard', ['view' => 'audition', 'participant_id' => $participant->id]),
                        'action_label' => 'Lihat E-Tiket',
                    ]);
                }
            }
        }

        $uniqueEventIds = $activeEventIds->unique()->values();
        if ($uniqueEventIds->isNotEmpty()) {
            $announcements = EventAnnouncement::query()
                ->with('event:id,name')
                ->where(function ($q) use ($uniqueEventIds) {
                    $q->whereIn('event_id', $uniqueEventIds)
                        ->orWhereNull('event_id');
                })
                ->orderByDesc('is_pinned')
                ->latest('id')
                ->take(3)
                ->get();

            foreach ($announcements as $announcement) {
                $targetTeam = $announcement->event_id
                    ? $teams->firstWhere('event_id', $announcement->event_id)
                    : $teams->first();

                $items->push([
                    'id' => "ann-{$announcement->id}",
                    'category' => 'Pengumuman',
                    'variant' => $announcement->is_pinned ? 'warning' : 'info',
                    'priority' => $announcement->is_pinned ? 1 : 2,
                    'title' => "Pengumuman: {$announcement->title}",
                    'message' => Str::limit(trim(strip_tags((string) $announcement->content)), 110),
                    'context_label' => $announcement->event?->name ?? 'Informasi Panitia',
                    'time_label' => $announcement->created_at?->diffForHumans(),
                    'href' => $targetTeam
                        ? route('manager.dashboard', ['view' => 'team', 'team_id' => $targetTeam->id])
                        : route('manager.dashboard'),
                    'action_label' => 'Baca Pengumuman',
                ]);
            }
        }

        return [
            'items' => $items->sortBy('priority')->values()->all(),
            'unpaid_total_count' => $unpaidTotalCount,
        ];
    }
}
