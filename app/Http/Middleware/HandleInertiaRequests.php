<?php

namespace App\Http\Middleware;

use App\Models\EmailNotificationLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Player;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\WebhookLog;
use App\Modules\Simulation\SimulationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
}
