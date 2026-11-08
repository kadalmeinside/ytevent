<?php

namespace App\Http\Controllers\Manager\Concerns;

use App\Models\Event;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

trait ResolvesActiveTeam
{
    protected function resolveManagerContext(Request $request): array
    {
        $user = $request->user();

        $teams = Team::query()
            ->with(['event', 'latestPayment', 'players.documents', 'officials'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $activeId = (int) $request->session()->get('active_manager_team_id', 0);
        $activeTeam = $teams->firstWhere('id', $activeId) ?? $teams->first();

        if ($activeTeam && $activeId !== $activeTeam->id) {
            $request->session()->put('active_manager_team_id', $activeTeam->id);
        }

        return [
            'activeTeam' => $activeTeam,
            'teamPayload' => $activeTeam ? $this->formatTeamForManager($activeTeam) : null,
            'teamsList' => $teams->map(fn (Team $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'status' => $t->status,
                'event_name' => $t->event?->name,
            ])->values(),
        ];
    }

    protected function formatTeamForManager(Team $team): array
    {
        $team->loadMissing(['event', 'latestPayment', 'players.documents', 'officials']);
        $event = $team->event;

        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'origin_association' => $team->origin_association,
            'secretariat_address' => $team->secretariat_address,
            'manager_name' => $team->manager_name,
            'manager_whatsapp' => $team->manager_whatsapp,
            'manager_email' => $team->manager_email,
            'head_coach_name' => $team->head_coach_name,
            'head_coach_license' => $team->head_coach_license,
            'home_jersey_color' => $team->home_jersey_color,
            'away_jersey_color' => $team->away_jersey_color,
            'gk_jersey_color' => $team->gk_jersey_color,
            'achievement_title' => $team->achievement_title,
            'status' => $team->status,
            'screening_status' => $team->screening_status,
            'slot_reserved_at' => $team->slot_reserved_at?->toIso8601String(),
            'slot_expires_at' => $team->slot_expires_at?->toIso8601String(),
            'roster_locked_at' => $team->roster_locked_at?->translatedFormat('d M Y H:i'),
            'can_manage_roster' => $team->canManageRoster(),
            'logo_url' => $team->logo_path ? Storage::disk('public')->url($team->logo_path) : null,
            'workflow' => $this->buildTeamWorkflow($team),
            'event' => $event ? [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'age_category' => $event->age_category,
                'format' => $event->format,
                'format_label' => Event::FORMATS[$event->format] ?? $event->format,
                'min_birth_date' => $event->min_birth_date?->toDateString(),
                'max_birth_date' => $event->max_birth_date?->toDateString(),
                'match_start_date' => $event->match_start_date?->format('d/m/Y'),
                'min_players_per_team' => $event->min_players_per_team,
                'max_players_per_team' => $event->max_players_per_team,
                'min_gk_per_team' => (int) ($event->min_gk_per_team ?? 2),
                'max_officials_per_team' => $event->max_officials_per_team,
                'show_coach_license' => (bool) ($event->show_coach_license ?? true),
                'require_coach_license' => (bool) (($event->show_coach_license ?? true) && ($event->require_coach_license ?? false)),
                'lock_roster_until_paid' => $event->lock_roster_until_paid,
                'registration_fee' => (float) $event->registration_fee,
                'required_documents' => collect($event->required_documents ?? [])->map(function ($item) {
                    $type = is_array($item) ? ($item['type'] ?? '') : (string) $item;
                    return [
                        'type' => $type,
                        'label' => is_array($item) && ! empty($item['label']) ? $item['label'] : Event::documentLabel($type),
                    ];
                })->values(),
            ] : null,
        ];
    }

    protected function buildTeamWorkflow(Team $team): array
    {
        $event = $team->event;
        $minPlayers = (int) ($event?->min_players_per_team ?? 11);
        $maxPlayers = (int) ($event?->max_players_per_team ?? 25);
        $minGk = (int) ($event?->min_gk_per_team ?? 2);
        $minOfficials = 1;
        $maxOfficials = (int) ($event?->max_officials_per_team ?? 5);
        $showCoachLicense = (bool) ($event?->show_coach_license ?? true);
        $requireCoachLicense = $showCoachLicense && (bool) ($event?->require_coach_license ?? false);
        $requiredDocTypes = collect($event?->required_documents ?? []);

        $players = $team->players;
        $playersTotal = $players->count();
        $playersDraft = $players->where('screening_status', 'draft')->count();
        $playersSubmitted = $players->where('screening_status', 'submitted')->count();
        $playersPassed = $players->where('screening_status', 'passed')->count();
        $playersRejected = $players->where('screening_status', 'rejected')->count();

        $playersDocsComplete = $players->filter(function ($player) use ($requiredDocTypes) {
            if (! $player->photo_path) {
                return false;
            }
            if ($requiredDocTypes->isEmpty()) {
                return true;
            }
            $uploadedTypes = $player->documents->pluck('document_type')->all();

            return $requiredDocTypes->every(fn ($type) => in_array($type, $uploadedTypes, true));
        })->count();

        $gkTotal = $players->where('position', 'GK')->count();
        $gkPassed = $players->where('position', 'GK')->where('screening_status', 'passed')->count();
        $officialsTotal = $team->officials->count();
        $headCoach = $team->officials->firstWhere('role', 'Head Coach');
        $hasCoachLicense = ! empty(trim((string) ($team->head_coach_license ?? '')))
            || ! empty(trim((string) ($headCoach?->license_number ?? '')));
        $isCoachLicenseMet = ! $requireCoachLicense || $hasCoachLicense;

        $paymentStatus = $team->latestPayment?->status ?? 'unpaid';
        $isPaid = in_array($team->status, ['active', 'locked_roster'], true) || $paymentStatus === 'paid';
        $isRosterLocked = $team->status === 'locked_roster';

        $isMinPlayersMet = $playersTotal >= $minPlayers;
        $isGkMet = $gkTotal >= $minGk;
        $isOfficialsMet = $officialsTotal >= $minOfficials && $isCoachLicenseMet;
        $isAllPlayersSubmittedOrPassed = $playersTotal >= $minPlayers && $playersDraft === 0 && $playersRejected === 0;
        $isAllPlayersPassed = $playersPassed >= $minPlayers
            && $playersPassed === $playersTotal
            && $playersDraft === 0
            && $playersSubmitted === 0
            && $playersRejected === 0;
        $isGkPassedMet = $gkPassed >= $minGk;

        $isReadyToLock = $isPaid
            && ! $isRosterLocked
            && $isAllPlayersPassed
            && $isGkPassedMet
            && $isOfficialsMet;

        if (! $isPaid) {
            $currentStep = 1;
        } elseif ($isRosterLocked) {
            $currentStep = 5;
        } elseif ($isReadyToLock) {
            $currentStep = 4;
        } elseif (! $isMinPlayersMet || ! $isGkMet || $playersDraft > 0 || $playersRejected > 0) {
            $currentStep = 2;
        } elseif (! $isOfficialsMet) {
            $currentStep = 3;
        } else {
            $currentStep = 4;
        }

        // Sidebar badges
        if ($isPaid) {
            $paymentBadge = ['text' => 'LUNAS', 'variant' => 'emerald', 'needs_action' => false];
        } elseif ($paymentStatus === 'waiting_verification') {
            $paymentBadge = ['text' => 'VERIFIKASI', 'variant' => 'amber', 'needs_action' => false];
        } else {
            $paymentBadge = ['text' => 'WAJIB', 'variant' => 'red', 'needs_action' => true];
        }

        if ($playersRejected > 0) {
            $playersBadge = ['text' => "{$playersRejected} REVISI", 'variant' => 'red', 'needs_action' => true, 'count' => $playersRejected];
        } elseif (! $isPaid) {
            $playersBadge = ['text' => 'TERKUNCI', 'variant' => 'slate', 'needs_action' => false, 'count' => 0];
        } elseif ($playersTotal < $minPlayers || $gkTotal < $minGk) {
            $playersBadge = ['text' => "{$playersTotal}/{$minPlayers}", 'variant' => 'amber', 'needs_action' => true, 'count' => max(0, $minPlayers - $playersTotal)];
        } elseif ($playersDraft > 0) {
            $playersBadge = ['text' => "{$playersDraft} DRAFT", 'variant' => 'amber', 'needs_action' => true, 'count' => $playersDraft];
        } elseif ($playersSubmitted > 0) {
            $playersBadge = ['text' => "{$playersSubmitted} PROSES", 'variant' => 'blue', 'needs_action' => false, 'count' => $playersSubmitted];
        } else {
            $playersBadge = ['text' => "{$playersPassed} SAH", 'variant' => 'emerald', 'needs_action' => false, 'count' => $playersPassed];
        }

        if (! $isPaid) {
            $officialsBadge = ['text' => "0/{$minOfficials}", 'variant' => 'slate', 'needs_action' => false, 'count' => 0];
        } elseif ($officialsTotal < $minOfficials) {
            $officialsBadge = ['text' => 'WAJIB 1+', 'variant' => 'amber', 'needs_action' => true, 'count' => 1];
        } elseif (! $isCoachLicenseMet) {
            $officialsBadge = ['text' => 'LISENSI WAJIB', 'variant' => 'amber', 'needs_action' => true, 'count' => 1];
        } else {
            $officialsBadge = ['text' => "{$officialsTotal}/{$maxOfficials}", 'variant' => 'emerald', 'needs_action' => false, 'count' => $officialsTotal];
        }

        if ($isRosterLocked) {
            $accreditationBadge = ['text' => 'FINAL', 'variant' => 'emerald', 'needs_action' => false, 'highlight' => false];
        } elseif ($isReadyToLock) {
            $accreditationBadge = ['text' => 'SIAP KUNCI', 'variant' => 'orange', 'needs_action' => true, 'highlight' => true];
        } else {
            $accreditationBadge = ['text' => 'BELUM SIAP', 'variant' => 'slate', 'needs_action' => false, 'highlight' => false];
        }

        // Contextual Notification Banner
        if ($isRosterLocked) {
            $banner = [
                'type' => 'accredited',
                'variant' => 'emerald',
                'step_label' => 'TAHAP 5 DARI 5 — SKUAD FINAL TERAKREDITASI RESMI',
                'title' => 'Skuad Final Terkunci & Terdaftar Resmi di Turnamen',
                'message' => "Seluruh {$playersPassed} pemain dan {$officialsTotal} ofisial tim {$team->name} telah terakreditasi resmi. Silakan unduh serta cetak Album Roster Sheet dan ID Card Pertandingan untuk dibawa saat Technical Meeting.",
                'cta_label' => 'Unduh ID Card & Roster',
                'cta_route' => 'manager.accreditation.index',
            ];
        } elseif ($isReadyToLock) {
            $banner = [
                'type' => 'ready_to_lock',
                'variant' => 'orange',
                'step_label' => 'PERINGATAN AKREDITASI — LANGKAH TERAKHIR WAJIB',
                'title' => 'Seluruh Pemain Telah Disahkan! Segera Kunci Skuad Final & Terbitkan ID Card',
                'message' => "Selamat! Seluruh {$playersPassed} pemain (termasuk {$gkPassed} Penjaga Gawang) dan {$officialsTotal} ofisial telah memenuhi syarat & disahkan panitia. Segera buka halaman Akreditasi & Skuad untuk menekan tombol Kunci Skuad Final agar ID Card dapat diunduh.",
                'cta_label' => 'Kunci Skuad Final Sekarang',
                'cta_route' => 'manager.accreditation.index',
            ];
        } elseif (! $isPaid) {
            $banner = [
                'type' => 'payment_required',
                'variant' => $paymentStatus === 'waiting_verification' ? 'amber' : 'red',
                'step_label' => 'TAHAP 1 DARI 5 — PEMBAYARAN PENDAFTARAN',
                'title' => $paymentStatus === 'waiting_verification'
                    ? 'Bukti Pembayaran Sedang Diverifikasi Panitia Keuangan'
                    : 'Segera Selesaikan Pembayaran Pendaftaran Tim',
                'message' => $paymentStatus === 'waiting_verification'
                    ? 'Panitia sedang memverifikasi bukti transfer Anda. Akses pendaftaran pemain dan berkas screening akan terbuka otomatis setelah pembayaran dinyatakan LUNAS.'
                    : 'Kuota tim Anda masih bersifat reservasi sementara. Selesaikan pembayaran sebelum batas waktu berakhir agar akses input pemain dan berkas screening terbuka.',
                'cta_label' => 'Buka Halaman Pembayaran',
                'cta_route' => 'manager.payments',
            ];
        } elseif ($playersRejected > 0) {
            $banner = [
                'type' => 'revision_required',
                'variant' => 'red',
                'step_label' => 'PERINGATAN SCREENING — PERBAIKAN DATA PEMAIN',
                'title' => "Terdapat {$playersRejected} Pemain Ditolak yang Wajib Direvisi",
                'message' => 'Panitia Screening memberikan catatan penolakan pada berkas pemain Anda. Silakan perbaiki dokumen sesuai catatan panitia lalu klik Ajukan Screening kembali agar proses akreditasi tidak terhambat.',
                'cta_label' => 'Perbaiki Data Pemain',
                'cta_route' => 'manager.players',
            ];
        } elseif ($playersTotal < $minPlayers || $gkTotal < $minGk || $playersDraft > 0) {
            $banner = [
                'type' => 'roster_incomplete',
                'variant' => 'amber',
                'step_label' => 'TAHAP 2 DARI 5 — KELENGKAPAN SKUAD & PENGAJUAN SCREENING',
                'title' => 'Lengkapi Kuota Skuad Pemain & Ajukan Berkas Screening',
                'message' => "Syarat wajib turnamen: minimal {$minPlayers} pemain (saat ini {$playersTotal}), minimal {$minGk} Penjaga Gawang/GK (saat ini {$gkTotal}), dan seluruh pemain berstatus DRAFT ({$playersDraft} pemain) harus diajukan ke panitia.",
                'cta_label' => 'Kelola Daftar Pemain',
                'cta_route' => 'manager.players',
            ];
        } elseif (! $isOfficialsMet) {
            $banner = [
                'type' => 'officials_incomplete',
                'variant' => 'amber',
                'step_label' => 'TAHAP 3 DARI 5 — DATA PELATIH & OFISIAL TIM',
                'title' => ! $isCoachLicenseMet
                    ? 'Lengkapi Nomor Lisensi Pelatih Kepala (Wajib Sesuai Regulasi Event)'
                    : 'Syarat Pemain Terpenuhi! Sekarang Tambahkan Minimal 1 Pelatih / Ofisial',
                'message' => ! $isCoachLicenseMet
                    ? 'Kompetisi ini mewajibkan pencantuman nomor lisensi Pelatih Kepala (Head Coach). Silakan lengkapi nomor lisensi pelatih di menu Pelatih & Ofisial sebelum mengunci skuad final.'
                    : 'Data pemain Anda sudah diajukan, namun setiap tim wajib mendaftarkan minimal 1 Pelatih atau Ofisial resmi sebelum skuad dapat dikunci untuk akreditasi.',
                'cta_label' => ! $isCoachLicenseMet ? 'Lengkapi Lisensi Pelatih' : 'Tambah Pelatih & Ofisial',
                'cta_route' => 'manager.officials',
            ];
        } else {
            $banner = [
                'type' => 'waiting_screening',
                'variant' => 'blue',
                'step_label' => 'TAHAP 4 DARI 5 — MENUNGGU VERIFIKASI SCREENING PANITIA',
                'title' => "Berkas {$playersSubmitted} Pemain Sedang Diperiksa Tim Screening",
                'message' => "Saat ini {$playersPassed} dari {$playersTotal} pemain telah dinyatakan SAH. Begitu seluruh pemain lolos verifikasi, Anda akan menerima peringatan untuk mengunci skuad final dan mencetak ID Card.",
                'cta_label' => 'Pantau Status Pemain',
                'cta_route' => 'manager.players',
            ];
        }

        return [
            'current_step' => $currentStep,
            'total_steps' => 5,
            'is_paid' => $isPaid,
            'payment_status' => $paymentStatus,
            'is_min_players_met' => $isMinPlayersMet,
            'is_gk_met' => $isGkMet,
            'is_officials_met' => $isOfficialsMet,
            'is_coach_license_met' => $isCoachLicenseMet,
            'show_coach_license' => $showCoachLicense,
            'require_coach_license' => $requireCoachLicense,
            'is_all_players_submitted_or_passed' => $isAllPlayersSubmittedOrPassed,
            'is_all_players_passed' => $isAllPlayersPassed,
            'is_gk_passed_met' => $isGkPassedMet,
            'is_ready_to_lock' => $isReadyToLock,
            'is_roster_locked' => $isRosterLocked,
            'counts' => [
                'players_total' => $playersTotal,
                'players_draft' => $playersDraft,
                'players_submitted' => $playersSubmitted,
                'players_passed' => $playersPassed,
                'players_rejected' => $playersRejected,
                'players_docs_complete' => $playersDocsComplete,
                'gk_total' => $gkTotal,
                'gk_passed' => $gkPassed,
                'officials_total' => $officialsTotal,
                'min_players_required' => $minPlayers,
                'max_players_allowed' => $maxPlayers,
                'min_gk_required' => $minGk,
                'min_officials_required' => $minOfficials,
                'max_officials_allowed' => $maxOfficials,
            ],
            'badges' => [
                'payment' => $paymentBadge,
                'players' => $playersBadge,
                'officials' => $officialsBadge,
                'accreditation' => $accreditationBadge,
            ],
            'banner' => $banner,
        ];
    }
}

