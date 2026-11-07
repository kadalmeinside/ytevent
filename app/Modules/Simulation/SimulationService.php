<?php

namespace App\Modules\Simulation;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\TeamOfficial;
use App\Models\User;
use App\Services\AccreditationService;
use App\Services\PaymentService;
use App\Services\ScreeningService;
use App\Services\TeamRegistrationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SimulationService
{
    public function __construct(
        protected TeamRegistrationService $teamRegistrationService,
        protected PaymentService $paymentService,
        protected ScreeningService $screeningService,
        protected AccreditationService $accreditationService
    ) {}

    /**
     * Mengecek apakah modul simulasi sedang aktif.
     */
    public static function isEnabled(): bool
    {
        if (! config('simulation.master_enabled', true)) {
            return false;
        }

        $setting = SystemSetting::get('simulation_mode_enabled');
        if ($setting === null) {
            return true;
        }

        return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Mengubah status aktif/nonaktif modul simulasi di database system_settings.
     */
    public static function setEnabled(bool $enabled): void
    {
        SystemSetting::set('simulation_mode_enabled', $enabled ? '1' : '0', 'boolean', 'simulation');
    }

    /**
     * Membuat berkas gambar PNG valid di memori/disk untuk simulasi logo, pas foto, bukti transfer, dan dokumen.
     */
    public function createDummyPngUploadedFile(string $filename = 'simulation.png'): UploadedFile
    {
        // 1x1 pixel valid PNG binary
        $pngBinary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $tempPath = sys_get_temp_dir() . '/' . Str::uuid()->toString() . '_' . $filename;
        file_put_contents($tempPath, $pngBinary);

        return new UploadedFile(
            $tempPath,
            $filename,
            'image/png',
            null,
            true
        );
    }

    /**
     * Menyimpan file PNG dummy langsung ke disk ('public' atau 'private') dan mengembalikan path-nya.
     */
    public function storeDummyPngOnDisk(string $directory, string $disk = 'private', string $prefix = 'sim'): string
    {
        $pngBinary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkA=='
        ) ?: base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $path = trim($directory, '/') . '/' . $prefix . '-' . Str::lower(Str::random(10)) . '.png';
        Storage::disk($disk)->put($path, $pngBinary);

        return $path;
    }

    /**
     * 1. Simulasi Pendaftaran Tim Baru secara instan (beserta akun manajer & logo dummy).
     */
    public function simulateRegisterTeam(?Event $event = null, ?User $authenticatedUser = null): Team
    {
        if (! $event) {
            $event = Event::query()
                ->where('status', 'open')
                ->orderByDesc('id')
                ->first();
        }

        if (! $event) {
            $event = Event::create([
                'name' => 'Young Tiger Simulation Cup ' . now()->format('Y'),
                'slug' => 'young-tiger-sim-cup-' . Str::lower(Str::random(4)),
                'short_description' => 'Kompetisi sepak bola usia muda resmi Persija Development.',
                'venue_name' => 'Nirwana Park Sawangan',
                'city' => 'Depok',
                'format' => 'cup',
                'age_category' => 'U-12',
                'min_birth_date' => '2014-01-01',
                'max_birth_date' => '2015-12-31',
                'min_players_per_team' => 12,
                'max_players_per_team' => 18,
                'min_gk_per_team' => 2,
                'max_officials_per_team' => 5,
                'show_coach_license' => true,
                'require_coach_license' => true,
                'required_documents' => ['birth_certificate', 'family_card', 'kia_or_nik', 'report_card_or_diploma', 'ssb_letter'],
                'max_teams' => 16,
                'registration_fee' => 2500000,
                'lock_roster_until_paid' => true,
                'payment_deadline_hours' => 24,
                'status' => 'open',
            ]);
        }

        // Jika kuota penuh atau tanggal lewat pada saat simulasi paksa, buka kapasitas tambahan
        if ($event->occupiedSlotsCount() >= $event->max_teams || $event->status !== 'open') {
            $event->update([
                'max_teams' => max($event->max_teams + 4, $event->occupiedSlotsCount() + 2),
                'status' => 'open',
            ]);
        }

        $clubNames = [
            'SSB Macan Muda Kemayoran',
            'Akademi Persija Muda',
            'SSB Bintang Timur Jakarta',
            'SSB Garuda Jaya Menteng',
            'SSB Putra Selatan',
            'SSB Tunas Harapan Jaya',
            'SSB Elang Oranye Cengkareng',
            'SSB Rajawali Depok',
            'SSB Patriot Bekasi Muda',
            'SSB Tangsel Tiger Academy',
        ];

        $randSuffix = random_int(100, 999);
        $teamName = $clubNames[array_rand($clubNames)] . ' ' . $randSuffix;
        $email = 'sim.manager.' . $randSuffix . '.' . Str::lower(Str::random(3)) . '@persijadevelopment.id';
        $phone = '0812' . random_int(10000000, 99999999);

        // Jika user yang sedang login adalah admin atau sudah punya tim di event ini, buat akun manajer baru
        $useExistingUser = null;
        if ($authenticatedUser && $authenticatedUser->hasRole('team_manager') && ! $authenticatedUser->hasAnyRole(['superadmin', 'finance', 'screening_officer'])) {
            $alreadyInEvent = $event->teams()
                ->where('user_id', $authenticatedUser->id)
                ->whereIn('status', ['pending_payment', 'active', 'locked_roster'])
                ->exists();
            if (! $alreadyInEvent) {
                $useExistingUser = $authenticatedUser;
            }
        }

        $validated = [
            'team_name' => $teamName,
            'origin_association' => 'Askot PSSI Jakarta Pusat',
            'secretariat_address' => 'Jl. H. R. Rasuna Said Kav. C-22, Kuningan, Jakarta Selatan',
            'manager_name' => $useExistingUser ? $useExistingUser->name : ('Coach Manajer Sim ' . $randSuffix),
            'manager_whatsapp' => $phone,
            'email' => $email,
            'password' => 'Simulasi123',
            'head_coach_name' => 'Coach Hendri Susilo ' . $randSuffix,
            'head_coach_license' => 'AFC C - PSSI ' . $randSuffix,
            'terms_accepted' => true,
        ];

        $logoFile = $this->createDummyPngUploadedFile('logo-tim-simulasi.png');

        return $this->teamRegistrationService->registerNewTeam(
            event: $event,
            validated: $validated,
            logoFile: $logoFile,
            authenticatedUser: $useExistingUser
        );
    }

    /**
     * 2. Simulasi Unggah Bukti Pembayaran oleh Manajer (atau Pelunasan Langsung).
     */
    public function simulateManagerPayment(Team $team, bool $instantSettle = false, ?User $actor = null): Payment
    {
        $payment = $team->latestPayment;
        if (! $payment) {
            $payment = Payment::create([
                'invoice_number' => $this->teamRegistrationService->generateInvoiceNumber($team->event),
                'team_id' => $team->id,
                'event_id' => $team->event_id,
                'amount' => $team->event?->registration_fee ?? 1500000,
                'gateway_fee' => 0,
                'total_amount' => $team->event?->registration_fee ?? 1500000,
                'expires_at' => now()->addHours(24),
                'status' => 'unpaid',
            ]);
        }

        if ($payment->status === 'paid') {
            return $payment;
        }

        $proofPath = $this->storeDummyPngOnDisk('payments/proofs', 'private', 'bukti-bayar-sim');

        $payment->update([
            'method' => 'manual',
            'payment_channel' => 'Transfer Bank Manual (Simulasi)',
            'sender_bank_name' => 'BCA',
            'sender_account_holder' => $team->manager_name,
            'sender_account_number' => '5240' . random_int(100000, 999999),
            'transfer_date' => now()->toDateString(),
            'proof_file_path' => $proofPath,
            'status' => 'waiting_verification',
            'rejection_reason' => null,
        ]);

        if ($instantSettle) {
            $verifier = $actor ?: User::role('superadmin')->first() ?: $team->manager;
            return $this->paymentService->approveManualPayment($payment->fresh(), $verifier);
        }

        return $payment->fresh();
    }

    /**
     * 3. Simulasi Pengisian Skuad Pemain, Ofisial, Pas Foto &Seluruh Dokumen Wajib sekaligus.
     */
    public function simulateFillRosterAndDocuments(Team $team, bool $autoSubmit = true): int
    {
        $team->loadMissing(['event', 'players', 'officials']);
        $event = $team->event;

        // Pastikan status tim aktif terlebih dahulu agar tidak terkunci pembayaran
        if ($team->status === 'pending_payment') {
            $this->simulateManagerPayment($team, true);
            $team->refresh();
        }

        if ($team->status === 'locked_roster') {
            $team->update([
                'status' => 'active',
                'roster_locked_at' => null,
            ]);
        }

        // Pastikan minimal 2 ofisial (Head Coach berlisensi + Assistant Coach) lengkap dengan foto & file lisensi
        $headCoach = $team->officials()->where('role', 'Head Coach')->first();
        if (! $headCoach) {
            $headCoach = TeamOfficial::create([
                'team_id' => $team->id,
                'event_id' => $team->event_id,
                'full_name' => $team->head_coach_name ?: 'Coach Bambang Pamungkas',
                'role' => 'Head Coach',
                'license_number' => $team->head_coach_license ?: 'AFC C - 2026',
                'phone' => '081288881111',
                'photo_path' => $this->storeDummyPngOnDisk('officials/photos', 'private', 'coach-photo'),
                'license_file_path' => $this->storeDummyPngOnDisk('officials/licenses', 'private', 'coach-license'),
                'screening_status' => 'draft',
            ]);
        } else {
            $headCoach->update([
                'license_number' => $headCoach->license_number ?: 'AFC C - 2026',
                'phone' => $headCoach->phone ?: '081288881111',
                'photo_path' => $headCoach->photo_path ?: $this->storeDummyPngOnDisk('officials/photos', 'private', 'coach-photo'),
                'license_file_path' => $headCoach->license_file_path ?: $this->storeDummyPngOnDisk('officials/licenses', 'private', 'coach-license'),
            ]);
        }

        if (! $team->head_coach_license) {
            $team->update([
                'head_coach_name' => $headCoach->full_name,
                'head_coach_license' => $headCoach->license_number,
            ]);
        }

        if ($team->officials()->count() < 2 && ($event?->max_officials_per_team ?? 5) >= 2) {
            TeamOfficial::create([
                'team_id' => $team->id,
                'event_id' => $team->event_id,
                'full_name' => 'Coach Ismed Sofyan (Asisten)',
                'role' => 'Assistant Coach',
                'license_number' => 'Lisensi D PSSI',
                'phone' => '081288882222',
                'photo_path' => $this->storeDummyPngOnDisk('officials/photos', 'private', 'asst-photo'),
                'license_file_path' => $this->storeDummyPngOnDisk('officials/licenses', 'private', 'asst-license'),
                'screening_status' => 'draft',
            ]);
        }

        $minPlayers = max(2, (int) ($event?->min_players_per_team ?? 12));
        $minGk = max(1, (int) ($event?->min_gk_per_team ?? 2));
        $requiredDocs = collect($event?->required_documents ?? ['birth_certificate', 'family_card', 'kia_or_nik', 'report_card_or_diploma', 'ssb_letter']);

        // Hitung tanggal lahir yang valid di antara min_birth_date & max_birth_date
        $birthDate = '2014-05-15';
        if ($event?->min_birth_date && $event?->max_birth_date) {
            $minTs = $event->min_birth_date->timestamp;
            $maxTs = $event->max_birth_date->timestamp;
            $midTs = (int) round(($minTs + $maxTs) / 2);
            $birthDate = date('Y-m-d', $midTs);
        } elseif ($event?->min_birth_date) {
            $birthDate = $event->min_birth_date->copy()->addMonths(3)->toDateString();
        } elseif ($event?->max_birth_date) {
            $birthDate = $event->max_birth_date->copy()->subMonths(3)->toDateString();
        }

        $firstNames = ['Rizky', 'Muhammad', 'Dony', 'Rayhan', 'Ilham', 'Rio', 'Fajar', 'Zahaby', 'Alfriyanto', 'Raka', 'Aditya', 'Bagas', 'Dimas', 'Naufal', 'Farhan', 'Gilang', 'Rendy', 'Arjuna'];
        $lastNames = ['Ridho', 'Ferarri', 'Tri Pamungkas', 'Hannan', 'Rio Fahmi', 'Fathurrahman', 'Gholy', 'Nico', 'Cahya', 'Pratama', 'Saputra', 'Ramadhan', 'Wijaya', 'Kusuma', 'Maulana', 'Hidayat', 'Santoso', 'Putra'];

        $existingCount = $team->players()->count();
        $targetCount = max($minPlayers, $existingCount);
        $usedNumbers = $team->players()->pluck('jersey_number')->all();

        $gkCount = $team->players()->where('position', 'GK')->count();
        $createdOrUpdated = 0;

        // Buat pemain baru hingga memenuhi min_players_per_team
        for ($i = $existingCount + 1; $i <= $targetCount; $i++) {
            $jersey = $i;
            while (in_array($jersey, $usedNumbers, true)) {
                $jersey++;
            }
            $usedNumbers[] = $jersey;

            $position = 'MF';
            if ($gkCount < $minGk) {
                $position = 'GK';
                $gkCount++;
            } elseif ($i % 3 === 0) {
                $position = 'DF';
            } elseif ($i % 3 === 1) {
                $position = 'FW';
            }

            do {
                $nik = '317' . random_int(1000000000000, 9999999999999);
            } while (Player::withTrashed()->where('event_id', $team->event_id)->where('nik', $nik)->exists());

            $nisn = (string) random_int(1000000000, 9999999999);
            $fullName = $firstNames[($i - 1) % count($firstNames)] . ' ' . $lastNames[($i - 1) % count($lastNames)];
            $jerseyName = strtoupper(explode(' ', $fullName)[0]);

            $player = Player::create([
                'team_id' => $team->id,
                'event_id' => $team->event_id,
                'full_name' => $fullName,
                'jersey_name' => $jerseyName,
                'jersey_number' => $jersey,
                'position' => $position,
                'dominant_foot' => $i % 4 === 0 ? 'Left' : 'Right',
                'birth_place' => 'Jakarta',
                'birth_date' => $birthDate,
                'nik' => $nik,
                'nisn' => $nisn,
                'height_cm' => random_int(145, 172),
                'weight_kg' => random_int(38, 62),
                'photo_path' => $this->storeDummyPngOnDisk('players/photos', 'private', 'player-photo'),
                'screening_status' => 'draft',
                'is_locked' => false,
            ]);

            $createdOrUpdated++;
        }

        // Lengkapi foto & seluruh dokumen wajib untuk semua pemain di tim ini
        $allPlayers = $team->players()->with('documents')->get();
        foreach ($allPlayers as $player) {
            if (! $player->photo_path) {
                $player->update([
                    'photo_path' => $this->storeDummyPngOnDisk('players/photos', 'private', 'player-photo'),
                ]);
            }

            $existingDocs = $player->documents->keyBy('document_type');
            foreach ($requiredDocs as $docType) {
                if (! $existingDocs->has($docType)) {
                    PlayerDocument::create([
                        'player_id' => $player->id,
                        'document_type' => $docType,
                        'file_path' => $this->storeDummyPngOnDisk('players/documents', 'private', $docType),
                        'original_name' => $docType . '-simulasi.png',
                        'file_size' => 1024,
                        'mime_type' => 'image/png',
                        'validation_status' => 'pending',
                    ]);
                }
            }

            if ($autoSubmit && in_array($player->screening_status, ['draft', 'rejected'], true)) {
                $player->update([
                    'screening_status' => 'submitted',
                    'rejection_reason' => null,
                ]);
            }
        }

        $this->screeningService->syncTeamScreeningStatus($team->fresh());

        return $allPlayers->count();
    }

    /**
     * 4. Simulasi Admin: Setujui Semua Pembayaran Menunggu Verifikasi & Lunasi Pembayaran Tim.
     */
    public function simulateAdminApprovePayments(?Team $specificTeam = null, ?User $actor = null): int
    {
        $verifier = $actor ?: User::role('superadmin')->first() ?: User::first();

        $query = Payment::query()->whereIn('status', ['waiting_verification', 'unpaid']);
        if ($specificTeam) {
            $query->where('team_id', $specificTeam->id);
        }

        $payments = $query->get();
        $approvedCount = 0;

        foreach ($payments as $payment) {
            if ($payment->status === 'unpaid') {
                $payment->update([
                    'method' => 'manual',
                    'payment_channel' => 'Transfer Bank Manual (Simulasi Admin)',
                    'sender_bank_name' => 'BCA',
                    'sender_account_holder' => $payment->team?->manager_name ?? 'Manajer Simulasi',
                    'sender_account_number' => '5240998877',
                    'transfer_date' => now()->toDateString(),
                    'proof_file_path' => $payment->proof_file_path ?: $this->storeDummyPngOnDisk('payments/proofs', 'private', 'bukti-sim'),
                    'status' => 'waiting_verification',
                ]);
            }

            $this->paymentService->approveManualPayment($payment->fresh(), $verifier);
            $approvedCount++;
        }

        return $approvedCount;
    }

    /**
     * 5. Simulasi Admin: Verifikasi & Sahkan Semua Pemain + Ofisial pada Tim (atau Seluruh Antrean).
     */
    public function simulateAdminApproveScreening(?Team $specificTeam = null, ?User $actor = null): int
    {
        $officer = $actor ?: User::role('superadmin')->first() ?: User::first();

        $playerQuery = Player::query()->whereIn('screening_status', ['submitted', 'draft', 'rejected']);
        if ($specificTeam) {
            $playerQuery->where('team_id', $specificTeam->id);
        } else {
            // Jika dari antrean global, utamakan yang submitted atau semua pemain pada tim aktif
            $playerQuery->whereHas('team', fn ($q) => $q->whereIn('status', ['active', 'locked_roster']));
        }

        $players = $playerQuery->get();
        $verifiedCount = 0;

        foreach ($players as $player) {
            $this->screeningService->verifyPlayer($player, $officer, 'passed');
            $verifiedCount++;
        }

        // Sahkan juga seluruh ofisial
        $officialQuery = TeamOfficial::query()->where('screening_status', '!=', 'passed');
        if ($specificTeam) {
            $officialQuery->where('team_id', $specificTeam->id);
        }
        $officialQuery->update([
            'screening_status' => 'passed',
            'verification_notes' => null,
        ]);

        return $verifiedCount;
    }

    /**
     * 6. Simulasi 1-Klik Penuh (Full End-to-End Fast-Forward hingga Skuad Terkunci & Siap Cetak ID Card).
     */
    public function simulateFullTeamToLockedRoster(?Team $team = null, ?Event $event = null, ?User $actor = null): Team
    {
        if (! $team) {
            $team = $this->simulateRegisterTeam($event, $actor);
        }

        // Tahap 1: Lunasi Pembayaran
        $this->simulateManagerPayment($team, true, $actor);
        $team->refresh();

        // Tahap 2 & 3: Isi Skuad Pemain, Ofisial & Dokumen Lengkap
        $this->simulateFillRosterAndDocuments($team, true);
        $team->refresh();

        // Tahap 4: Sahkan seluruh Pemain & Ofisial
        $this->simulateAdminApproveScreening($team, $actor);
        $team->refresh();

        // Tahap 5: Kunci Skuad Final (locked_roster)
        if ($team->status !== 'locked_roster') {
            $lockActor = $actor ?: $team->manager ?: User::role('superadmin')->first();
            $this->accreditationService->lockRoster($team, $lockActor);
        }

        return $team->fresh(['event', 'players', 'officials', 'latestPayment']);
    }
}

