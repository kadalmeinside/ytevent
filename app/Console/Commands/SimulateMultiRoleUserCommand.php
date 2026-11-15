<?php

namespace App\Console\Commands;

use App\Modules\Simulation\SimulationService;
use Illuminate\Console\Command;

class SimulateMultiRoleUserCommand extends Command
{
    protected $signature = 'simulation:seed-multi-role {--email= : Opsional email akun target}';

    protected $description = 'Simulasi akun lengkap (Multi-Peran): memiliki Tim Aktif, Tim Selesai, Anak Audisi Aktif, dan Anak Audisi Selesai (dengan sertifikat).';

    public function handle(SimulationService $simulationService): int
    {
        $this->info('Memulai pembuatan simulasi akun multi-peran (Tim & Audisi - Aktif & Selesai)...');

        $targetEmail = $this->option('email');
        $existingUser = $targetEmail ? \App\Models\User::where('email', $targetEmail)->first() : null;

        $data = $simulationService->simulateMultiRegistrationPersona($existingUser);

        $user = $data['user'];
        $activeTeam = $data['active_team'];
        $pastTeam = $data['past_team'];
        $activeAudition = $data['active_audition'];
        $pastAudition = $data['past_audition'];

        $this->newLine();
        $this->info('================================================================');
        $this->info('  AKUN SIMULASI MULTI-PERAN BERHASIL DISIAPKAN');
        $this->info('================================================================');
        $this->line("Nama Pengguna   : {$user->name}");
        $this->line("Email Login     : {$user->email}");
        $this->line("Password Default: password");
        $this->line("Peran Sistem    : " . implode(', ', $user->getRoleNames()->toArray()));
        $this->newLine();

        $this->info('1. TIM TURNAMEN AKTIF (Musim Berjalan):');
        $this->line("   - Tim   : {$activeTeam->name} (Status: {$activeTeam->status})");
        $this->line("   - Event : {$activeTeam->event?->name} (Status: {$activeTeam->event?->status})");
        $this->line("   - Pemain: {$activeTeam->players()->count()} pemain terverifikasi sah");
        $this->newLine();

        $this->info('2. TIM TURNAMEN YANG SUDAH LALU (Arsip Selesai 2025):');
        $this->line("   - Tim   : {$pastTeam->name} (Status: {$pastTeam->status})");
        $this->line("   - Event : {$pastTeam->event?->name} (Status: {$pastTeam->event?->status})");
        $this->newLine();

        $this->info('3. PENDAFTARAN ANAK AUDISI AKTIF (Musim 2026):');
        $this->line("   - Peserta  : {$activeAudition->full_name} (Bib #{$activeAudition->bib_number})");
        $this->line("   - Event    : {$activeAudition->event?->name}");
        $this->line("   - Status   : Lolos Berkas / Screening - Digital Pass & E-Tiket Aktif");
        $this->newLine();

        $this->info('4. PENDAFTARAN ANAK AUDISI YANG SUDAH LALU (Selesai 2025):');
        $this->line("   - Peserta  : {$pastAudition->full_name} (Bib #{$pastAudition->bib_number})");
        $this->line("   - Event    : {$pastAudition->event?->name} (Status: {$pastAudition->event?->status})");
        $this->line("   - Status   : {$pastAudition->stageStatusLabel()} - Piagam / Sertifikat Aktif");
        $this->newLine();

        $this->line("Login langsung di: " . url('/manager/dashboard'));
        $this->info('================================================================');

        return self::SUCCESS;
    }
}
