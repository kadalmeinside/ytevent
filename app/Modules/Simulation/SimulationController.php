<?php

namespace App\Modules\Simulation;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SimulationController extends Controller
{
    public function __construct(
        protected SimulationService $simulationService
    ) {}

    protected function ensureSimulationEnabled(): void
    {
        if (! SimulationService::isEnabled()) {
            throw new AccessDeniedHttpException('Modul Simulasi Cepat sedang dinonaktifkan.');
        }
    }

    /**
     * Menghidupkan atau mematikan sementara Modul Simulasi.
     */
    public function toggle(Request $request): RedirectResponse
    {
        if (! config('simulation.master_enabled', true)) {
            return redirect()->back()->with('error', 'Modul simulasi dikunci melalui konfigurasi server (.env).');
        }

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        SimulationService::setEnabled((bool) $validated['enabled']);

        $statusText = $validated['enabled'] ? 'DIHIDUPKAN' : 'DIMATIKAN SEMENTARA';

        return redirect()->back()->with('success', "Mode Simulasi Cepat berhasil {$statusText}.");
    }

    /**
     * Pindah akun cepat (Quick Switch Persona: Superadmin, Finance, Screening Officer, atau Manajer Tim).
     */
    public function switchPersona(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:superadmin,finance,screening_officer,team_manager'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ]);

        $role = $validated['role'];

        if ($role === 'team_manager') {
            $team = ! empty($validated['team_id'])
                ? Team::find($validated['team_id'])
                : Team::query()->latest('id')->first();

            if (! $team) {
                $team = $this->simulationService->simulateRegisterTeam();
            }

            Auth::login($team->manager);
            $request->session()->regenerate();
            $request->session()->put('active_manager_team_id', $team->id);

            return redirect()->route('manager.dashboard')
                ->with('success', "[SIMULASI] Beralih ke akun Manajer Tim: {$team->name} ({$team->manager_email}).");
        }

        $adminUser = User::role($role)->first();
        if (! $adminUser) {
            $adminUser = User::role('superadmin')->first();
        }

        if (! $adminUser) {
            return redirect()->back()->with('error', 'Akun panitia tidak ditemukan di database.');
        }

        Auth::login($adminUser);
        $request->session()->regenerate();

        $targetRoute = match ($role) {
            'finance' => 'admin.payments.manual-verification',
            'screening_officer' => 'admin.screening.index',
            default => 'admin.dashboard',
        };

        return redirect()->route($targetRoute)
            ->with('success', "[SIMULASI] Beralih ke akun Panitia ({$adminUser->name} — " . strtoupper($role) . ').');
    }

    /**
     * 1. Simulasi Pendaftaran Tim Baru (Publik / Manajer / Admin).
     */
    public function registerTeam(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = null;
        if ($eventId = $request->input('event_id')) {
            $event = Event::find($eventId);
        } elseif ($eventSlug = $request->input('event_slug')) {
            $event = Event::where('slug', $eventSlug)->first();
        }

        $loginAsManager = $request->boolean('login_as_manager', true);

        $team = $this->simulationService->simulateRegisterTeam($event, $request->user());

        if ($loginAsManager && (! $request->user() || ! $request->user()->hasAnyRole(['superadmin', 'finance', 'screening_officer']))) {
            Auth::login($team->manager);
            $request->session()->regenerate();
            $request->session()->put('active_manager_team_id', $team->id);

            return redirect()->route('manager.payments')
                ->with('success', "[SIMULASI] Tim '{$team->name}' berhasil didaftarkan secara otomatis! Lanjutkan ke simulasi pembayaran.");
        }

        if ($request->user()?->hasRole('team_manager')) {
            $request->session()->put('active_manager_team_id', $team->id);
        }

        return redirect()->back()
            ->with('success', "[SIMULASI] Tim baru '{$team->name}' berhasil dibuat pada kompetisi {$team->event?->name}.");
    }

    /**
     * 2. Simulasi Unggah Bukti Pembayaran / Pelunasan Tagihan Tim.
     */
    public function payTeam(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $team = $this->resolveTargetTeam($request);
        if (! $team) {
            return redirect()->back()->with('error', 'Belum ada tim untuk disimulasikan pembayarannya.');
        }

        $instantSettle = $request->boolean('instant_settle', false);
        $payment = $this->simulationService->simulateManagerPayment($team, $instantSettle, $request->user());

        $msg = $instantSettle
            ? "[SIMULASI] Pembayaran tim '{$team->name}' ({$payment->invoice_number}) berhasil dilunasi instan! Status tim kini AKTIF."
            : "[SIMULASI] Bukti transfer manual untuk tim '{$team->name}' ({$payment->invoice_number}) berhasil diunggah dan masuk ke antrean verifikasi Finance.";

        return redirect()->back()->with('success', $msg);
    }

    /**
     * 3. Simulasi Pengisian Skuad Pemain, Ofisial, Pas Foto & Dokumen Lengkap + Submit Screening.
     */
    public function fillRoster(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $team = $this->resolveTargetTeam($request);
        if (! $team) {
            $team = $this->simulationService->simulateRegisterTeam(null, $request->user());
            if ($request->user()?->hasRole('team_manager')) {
                $request->session()->put('active_manager_team_id', $team->id);
            }
        }

        $autoSubmit = $request->boolean('auto_submit', true);
        $totalPlayers = $this->simulationService->simulateFillRosterAndDocuments($team, $autoSubmit);

        $submitNote = $autoSubmit ? 'dan langsung diajukan ke antrean Screening Panitia' : 'dalam status Draft';

        return redirect()->back()
            ->with('success', "[SIMULASI] Skuad tim '{$team->name}' berhasil diisi paksa ({$totalPlayers} pemain beserta GK, Pelatih Berlisensi, Pas Foto & 5 Dokumen Wajib) {$submitNote}.");
    }

    /**
     * 4. Simulasi Admin: Verifikasi & Setujui Semua Pembayaran.
     */
    public function adminApprovePayments(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $specificTeam = $request->filled('team_id') ? Team::find($request->input('team_id')) : null;
        $count = $this->simulationService->simulateAdminApprovePayments($specificTeam, $request->user());

        if ($count === 0) {
            return redirect()->back()->with('info', '[SIMULASI] Tidak ada tagihan menunggu verifikasi yang perlu disetujui.');
        }

        return redirect()->back()
            ->with('success', "[SIMULASI ADMIN] {$count} transaksi pembayaran berhasil disetujui lunas (PAID) dan tim telah diaktifkan.");
    }

    /**
     * 5. Simulasi Admin: Verifikasi & Sahkan Semua Pemain + Ofisial.
     */
    public function adminApproveScreening(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $specificTeam = $request->filled('team_id') ? Team::find($request->input('team_id')) : null;
        $count = $this->simulationService->simulateAdminApproveScreening($specificTeam, $request->user());

        if ($count === 0) {
            return redirect()->back()->with('info', '[SIMULASI] Tidak ada pemain dalam antrean screening yang perlu diverifikasi.');
        }

        return redirect()->back()
            ->with('success', "[SIMULASI ADMIN] {$count} pemain beserta berkas dokumen dan lisensi ofisial berhasil disahkan (PASSED)!");
    }

    /**
     * 6. Simulasi 1-Klik Penuh (Daftar Tim -> Bayar Lunas -> Isi Pemain & Dokumen -> Lolos Screening -> Kunci Skuad Final).
     */
    public function fastForwardFullTeam(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $team = $this->resolveTargetTeam($request);
        $event = $request->filled('event_id') ? Event::find($request->input('event_id')) : null;

        $completedTeam = $this->simulationService->simulateFullTeamToLockedRoster($team, $event, $request->user());

        return redirect()->back()
            ->with('success', "[SIMULASI PENUH SELESAI] Tim '{$completedTeam->name}' telah tuntas 100% (Lunas -> Skuad & Dokumen Lengkap -> Lolos Screening -> Skuad Terkunci Final) dan siap cetak ID Card PDF!");
    }

    /**
     * 7. Simulasi Inject Sisa Kekurangan Tim Slot & Roster Pemain Lengkap untuk suatu Event.
     */
    public function fillEventSlots(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = $this->resolveTargetEvent($request);
        if (! $event) {
            return redirect()->back()->with('error', '[SIMULASI] Pilih event target terlebih dahulu.');
        }

        $result = $this->simulationService->simulateFillRemainingSlots($event, $request->user());

        return redirect()->back()->with('success', '[SIMULASI] ' . $result['message']);
    }

    /**
     * 8. Simulasi Sahkan Semua Berkas Pemain & Kunci Skuad Seluruh Tim di Event.
     */
    public function bulkApproveAndLockAll(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = $this->resolveTargetEvent($request);
        if (! $event) {
            return redirect()->back()->with('error', '[SIMULASI] Pilih event target terlebih dahulu.');
        }

        $result = $this->simulationService->simulateApproveAndLockAllTeams($event, $request->user());

        return redirect()->back()->with('success', '[SIMULASI] ' . $result['message']);
    }

    protected function resolveTargetEvent(Request $request): ?Event
    {
        if ($eventId = $request->input('event_id')) {
            $evt = Event::find($eventId);
            if ($evt) {
                return $evt;
            }
        }

        if ($team = $this->resolveTargetTeam($request)) {
            if ($team->event) {
                return $team->event;
            }
        }

        return Event::query()->where('status', '!=', 'draft')->latest('id')->first();
    }

    protected function resolveTargetTeam(Request $request): ?Team
    {
        if ($teamId = $request->input('team_id')) {
            return Team::find($teamId);
        }

        if ($sessionTeamId = $request->session()->get('active_manager_team_id')) {
            $team = Team::find($sessionTeamId);
            if ($team) {
                return $team;
            }
        }

        if ($user = $request->user()) {
            $ownTeam = Team::where('user_id', $user->id)->latest('id')->first();
            if ($ownTeam) {
                return $ownTeam;
            }
        }

        return Team::query()->latest('id')->first();
    }
}

