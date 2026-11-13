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
        if (! config('simulation.master_enabled', false)) {
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
            'role' => ['required', 'string', 'in:superadmin,finance,screening_officer,team_manager,scout,talent_scout,participant_parent'],
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

        if ($role === 'participant_parent') {
            $parent = User::role('participant_parent')->first();
            if (! $parent) {
                $participant = $this->simulationService->simulateRegisterIndividualParticipant();
                $parent = $participant->user;
            }

            Auth::login($parent);
            $request->session()->regenerate();

            return redirect()->route('manager.dashboard')
                ->with('success', "[SIMULASI] Beralih ke akun Orang Tua / Peserta Perorangan: {$parent->name} ({$parent->email}).");
        }

        if ($role === 'scout' || $role === 'talent_scout') {
            $scoutUser = User::role('talent_scout')->first();
            if (! $scoutUser) {
                $scoutUser = User::create([
                    'name' => 'Coach Scout Simulator',
                    'email' => 'scout.simulasi@persijadev.id',
                    'password' => bcrypt('password'),
                ]);
                $scoutUser->assignRole('talent_scout');
            }

            Auth::login($scoutUser);
            $request->session()->regenerate();

            $event = Event::where('participant_type', 'individual')->where('event_type', 'audition')->latest('id')->first();
            $targetRoute = $event ? route('scout.events.show', ['event' => $event->id]) : route('admin.dashboard');

            return redirect()->to($targetRoute)
                ->with('success', "[SIMULASI] Beralih ke akun Pemandu Bakat / Scout ({$scoutUser->name}).");
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

    /**
     * 9. Simulasi Hapus Bersih Tim Terdaftar (Beserta Tagihan, Pembayaran, Berkas & Pemain).
     */
    public function deleteTeam(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $team = $this->resolveTargetTeam($request);
        if (! $team) {
            return redirect()->back()->with('error', '[SIMULASI] Pilih tim yang ingin dihapus terlebih dahulu.');
        }

        $teamName = $team->name;
        $teamId = $team->id;
        $managerId = $team->user_id;

        $this->simulationService->purgeTeam($team);

        if ($request->session()->get('active_manager_team_id') == $teamId) {
            $request->session()->forget('active_manager_team_id');
        }

        // Jika user yang sedang login adalah manajer tim yang dihapus, logout dan alihkan ke beranda
        if (Auth::id() === $managerId && ! Auth::user()?->hasAnyRole(['superadmin', 'finance', 'screening_officer'])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('home')
                ->with('success', "[SIMULASI] Tim '{$teamName}' beserta seluruh tagihan, bukti pembayaran, dan berkas pemain telah dihapus bersih. Slot kuota event dibuka kembali.");
        }

        return redirect()->back()
            ->with('success', "[SIMULASI] Tim '{$teamName}' (#{$teamId}) beserta seluruh data tagihan/pembayaran dan berkas pemain telah dihapus bersih dari sistem!");
    }

    /**
     * 10. Simulasi Hapus Bersih Seluruh Tim Terdaftar pada Suatu Event.
     */
    public function deleteAllTeamsInEvent(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = $this->resolveTargetEvent($request);
        if (! $event) {
            return redirect()->back()->with('error', '[SIMULASI] Pilih event target terlebih dahulu.');
        }

        $count = $this->simulationService->purgeAllTeamsInEvent($event);

        $request->session()->forget('active_manager_team_id');

        return redirect()->back()
            ->with('success', "[SIMULASI] Sebanyak {$count} tim terdaftar pada kompetisi '{$event->name}' beserta seluruh data pembayaran dan berkasnya telah dihapus bersih. Kuota event kembali kosong (0 tim).");
    }

    /**
     * Simulasi Inject / Buat Event Audisi Lengkap (2 Tahap, Kuota Posisi & Sesi).
     */
    public function createAuditionEvent(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = $this->simulationService->simulateCreateAuditionEvent($request->user());

        return redirect()->route('events.show', ['event' => $event->slug])
            ->with('success', "[SIMULASI] Event Audisi baru '{$event->name}' berhasil dibuat lengkap beserta 2 tahapan seleksi, sesi, dan alokasi batas kuota per posisi!");
    }

    /**
     * Simulasi Inject / Buat Event Coaching Clinic Lengkap (Materi Pelatihan, Sesi Workshop & Sertifikat).
     */
    public function createCoachingClinicEvent(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = $this->simulationService->simulateCreateCoachingClinicEvent($request->user());

        return redirect()->route('events.show', ['event' => $event->slug])
            ->with('success', "[SIMULASI] Event Coaching Clinic baru '{$event->name}' berhasil dibuat lengkap beserta sesi masterclass pelatihan!");
    }

    /**
     * Simulasi Tambah 1 Peserta Khusus Event Audisi Seleksi.
     */
    public function registerAuditionParticipant(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = null;
        if ($eventId = $request->input('event_id')) {
            $event = Event::find($eventId);
        }
        if (! $event || $event->event_type !== 'audition') {
            $event = Event::where('participant_type', 'individual')->where('event_type', 'audition')->latest('id')->first();
        }

        $participant = $this->simulationService->simulateRegisterAuditionParticipant($event, $request->user());

        return redirect()->back()
            ->with('success', "[SIMULASI AUDISI] Atlet '{$participant->full_name}' (ID Digital Pass: {$participant->registration_number}) berhasil didaftarkan ke event '{$participant->event?->name}' dengan berkas lengkap & lolos verifikasi!");
    }

    /**
     * Simulasi Tambah 1 Peserta Khusus Event Coaching Clinic.
     */
    public function registerCoachingClinicParticipant(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = null;
        if ($eventId = $request->input('event_id')) {
            $event = Event::find($eventId);
        }
        if (! $event || $event->event_type !== 'coaching_clinic') {
            $event = Event::where('participant_type', 'individual')->where('event_type', 'coaching_clinic')->latest('id')->first();
        }

        $participant = $this->simulationService->simulateRegisterCoachingClinicParticipant($event, $request->user());

        return redirect()->back()
            ->with('success', "[SIMULASI CLINIC] Peserta '{$participant->full_name}' berhasil didaftarkan ke Coaching Clinic '{$participant->event?->name}' lengkap dengan sesi pelatihan & pembayaran lunas!");
    }

    /**
     * 11. Simulasi Pendaftaran 1 Peserta Perorangan (Audisi / Clinic).
     */
    public function registerIndividual(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = $this->resolveTargetEvent($request);
        $participant = $this->simulationService->simulateRegisterIndividualParticipant($event, $request->user());

        return redirect()->back()
            ->with('success', "[SIMULASI] Peserta baru '{$participant->full_name}' ({$participant->registration_number}) berhasil didaftarkan pada event {$participant->event?->name}!");
    }

    /**
     * 12. Simulasi Inject Massal Peserta Perorangan (Default: 6 Peserta Lunas & Sah).
     */
    public function bulkInjectIndividuals(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = $this->resolveTargetEvent($request);
        if (! $event || $event->participant_type !== 'individual') {
            $event = Event::where('participant_type', 'individual')->latest('id')->first();
        }
        if (! $event) {
            $dummy = $this->simulationService->simulateRegisterIndividualParticipant();
            $event = $dummy->event;
        }

        $count = (int) $request->input('count', 6);
        $result = $this->simulationService->simulateBulkInjectIndividualParticipants($event, $count);

        return redirect()->back()
            ->with('success', "[SIMULASI] Berhasil menyuntikkan {$result['count']} peserta perorangan sekaligus ke event '{$result['event_name']}'!");
    }

    /**
     * 13. Simulasi Presensi Hadir Gerbang (Check-in Massal).
     */
    public function bulkCheckIn(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = $this->resolveTargetEvent($request);
        if (! $event || $event->participant_type !== 'individual') {
            $event = Event::where('participant_type', 'individual')->latest('id')->first();
        }
        if (! $event) {
            return redirect()->back()->with('error', '[SIMULASI] Belum ada event perorangan untuk check-in.');
        }

        $stageNum = (int) $request->input('stage_number', 1);
        $count = $this->simulationService->simulateBulkCheckInAttendance($event, $stageNum);

        return redirect()->back()
            ->with('success', "[SIMULASI GATEKEEPER] {$count} peserta berhasil dicatat hadir (Presensi Tahap {$stageNum}) pada event {$event->name}!");
    }

    /**
     * 14. Simulasi Penilaian Scout Massal (Grading A/B/C & Rating Bintang).
     */
    public function bulkScoutGrade(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = $this->resolveTargetEvent($request);
        if (! $event || $event->participant_type !== 'individual') {
            $event = Event::where('participant_type', 'individual')->latest('id')->first();
        }
        if (! $event) {
            return redirect()->back()->with('error', '[SIMULASI] Belum ada event audisi untuk penilaian.');
        }

        $stageNum = (int) $request->input('stage_number', 1);
        $count = $this->simulationService->simulateBulkScoutGrading($event, $stageNum, $request->user());

        return redirect()->back()
            ->with('success', "[SIMULASI SCOUT] {$count} evaluasi penilaian pemandu bakat berhasil digenerate otomatis untuk Tahap {$stageNum}!");
    }

    /**
     * 15. Simulasi Loloskan Peserta ke Tahap Lanjutan.
     */
    public function bulkAdvanceStage(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = $this->resolveTargetEvent($request);
        if (! $event || $event->participant_type !== 'individual') {
            $event = Event::where('participant_type', 'individual')->latest('id')->first();
        }
        if (! $event) {
            return redirect()->back()->with('error', '[SIMULASI] Belum ada event audisi untuk kelolosan.');
        }

        $targetStatus = $request->input('target_status', 'day_1_passed');
        $count = $this->simulationService->simulateBulkAdvanceStage($event, $targetStatus);

        return redirect()->back()
            ->with('success', "[SIMULASI KELOLOSAN] {$count} peserta berhasil dipromosikan status kelolosannya ({$targetStatus})!");
    }

    /**
     * 16. Simulasi Hapus Bersih Seluruh Peserta pada Suatu Event Perorangan.
     */
    public function deleteAllIndividualsInEvent(Request $request): RedirectResponse
    {
        $this->ensureSimulationEnabled();

        $event = $this->resolveTargetEvent($request);
        if (! $event) {
            return redirect()->back()->with('error', '[SIMULASI] Pilih event perorangan terlebih dahulu.');
        }

        $count = $this->simulationService->purgeAllIndividualsInEvent($event);

        return redirect()->back()
            ->with('success', "[SIMULASI RESET] Seluruh data ({$count} peserta perorangan, presensi, berkas & penilaian) pada event '{$event->name}' berhasil dibersihkan.");
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

