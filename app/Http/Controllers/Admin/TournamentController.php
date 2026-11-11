<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\EventGroup;
use App\Models\EventGroupTeam;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamOfficial;
use App\Models\TournamentMatch;
use App\Services\CertificateService;
use App\Services\StandingsCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    public function __construct(
        protected StandingsCalculatorService $standingsService,
        protected CertificateService $certificateService,
    ) {}

    /**
     * Halaman Pusat Kendali Turnamen, Grup, Jadwal Pertandingan, Skor, & Pengumuman per Event.
     */
    public function show(Event $event): Response
    {
        $event->load([
            'groups.groupTeams.team',
            'matches.homeTeam',
            'matches.awayTeam',
            'matches.group',
            'matches.matchEvents.player:id,full_name,jersey_number,position',
            'matches.matchEvents.team:id,name',
            'announcements.author:id,name',
        ]);

        $eligibleTeams = $event->teams()
            ->whereIn('status', ['locked_roster', 'active'])
            ->with([
                'players' => fn ($q) => $q->where('screening_status', 'passed')->orderBy('jersey_number'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Team $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'status' => $t->status,
                'origin_association' => $t->origin_association,
                'home_jersey_color' => $t->home_jersey_color,
                'away_jersey_color' => $t->away_jersey_color,
                'gk_jersey_color' => $t->gk_jersey_color,
                'achievement_title' => $t->achievement_title,
                'players' => $t->players->map(fn (Player $p) => [
                    'id' => $p->id,
                    'full_name' => $p->full_name,
                    'jersey_number' => $p->jersey_number,
                    'position' => $p->position,
                ])->values(),
            ])
            ->values();

        $groups = $event->groups->map(fn (EventGroup $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'sort_order' => $g->sort_order,
            'standings' => $g->groupTeams->map(fn (EventGroupTeam $gt) => [
                'id' => $gt->id,
                'team_id' => $gt->team_id,
                'team_name' => $gt->team?->name ?? 'Tim Terhapus',
                'played' => $gt->played,
                'won' => $gt->won,
                'drawn' => $gt->drawn,
                'lost' => $gt->lost,
                'goals_for' => $gt->goals_for,
                'goals_against' => $gt->goals_against,
                'goal_difference' => $gt->goal_difference,
                'points' => $gt->points,
                'yellow_cards' => $gt->yellow_cards,
                'red_cards' => $gt->red_cards,
                'manual_rank_override' => $gt->manual_rank_override,
            ])->values(),
        ])->values();

        $matches = $event->matches->map(fn (TournamentMatch $m) => [
            'id' => $m->id,
            'match_number' => $m->match_number,
            'stage' => $m->stage,
            'stage_label' => TournamentMatch::stageLabel($m->stage),
            'event_group_id' => $m->event_group_id,
            'group_name' => $m->group?->name,
            'home_team_id' => $m->home_team_id,
            'home_team_name' => $m->homeTeam?->name ?? 'TBD',
            'home_jersey_color' => $m->homeTeam?->home_jersey_color,
            'away_team_id' => $m->away_team_id,
            'away_team_name' => $m->awayTeam?->name ?? 'TBD',
            'away_jersey_color' => $m->awayTeam?->away_jersey_color ?: $m->awayTeam?->home_jersey_color,
            'kickoff_at' => $m->kickoff_at?->format('Y-m-d\TH:i'),
            'kickoff_formatted' => $m->kickoff_at?->translatedFormat('d M Y, H:i') . ($m->kickoff_at ? ' WIB' : ''),
            'pitch_name' => $m->pitch_name,
            'status' => $m->status,
            'status_label' => TournamentMatch::STATUSES[$m->status] ?? $m->status,
            'home_score' => $m->home_score,
            'away_score' => $m->away_score,
            'home_penalty_score' => $m->home_penalty_score,
            'away_penalty_score' => $m->away_penalty_score,
            'referee_name' => $m->referee_name,
            'match_notes' => $m->match_notes,
            'events' => $m->matchEvents->map(fn (MatchEvent $ev) => [
                'id' => $ev->id,
                'team_id' => $ev->team_id,
                'team_name' => $ev->team?->name,
                'player_id' => $ev->player_id,
                'player_name' => $ev->player?->full_name,
                'jersey_number' => $ev->player?->jersey_number,
                'event_type' => $ev->event_type,
                'minute' => $ev->minute,
                'notes' => $ev->notes,
            ])->values(),
        ])->values();

        $announcements = $event->announcements->map(fn (EventAnnouncement $a) => [
            'id' => $a->id,
            'title' => $a->title,
            'content' => $a->content,
            'meeting_link' => $a->meeting_link,
            'is_pinned' => $a->is_pinned,
            'author_name' => $a->author?->name ?? 'Panitia',
            'created_at' => $a->created_at?->translatedFormat('d M Y, H:i'),
        ])->values();

        $occupiedSlots = $event->occupiedSlotsCount();
        $isRegistrationOpen = $event->status === 'open' && $event->isWithinRegistrationWindow() && $occupiedSlots < $event->max_teams;
        $groupsCount = $event->groups->count();
        $hasPlayedMatches = $event->matches()->where('status', '!=', 'scheduled')->exists();
        $allMatchesCount = $event->matches->count();
        $uncompletedMatchesCount = $event->matches()->where('status', '!=', 'completed')->count();
        $hasPendingGroupMatches = $event->matches()->where('stage', 'group')->where('status', '!=', 'completed')->exists();

        // 1. Undi Grup: Boleh jika pendaftaran SUDAH TIDAK BUKA (kuota penuh / pendaftaran ditutup) & min 2 tim & belum dikunci & belum ada laga
        $canDrawGroups = ! $isRegistrationOpen && $eligibleTeams->count() >= 2 && ! $event->is_group_locked && ! $hasPlayedMatches;
        $drawBlockReason = null;
        if ($isRegistrationOpen) {
            $drawBlockReason = "Pendaftaran masih OPEN dan slot kuota belum penuh ({$occupiedSlots}/{$event->max_teams} tim). Pengundian grup baru dapat dilakukan saat sesi Technical Meeting setelah kuota penuh atau pendaftaran resmi ditutup.";
        } elseif ($event->is_group_locked) {
            $drawBlockReason = 'Pembagian grup telah resmi dikunci panitia. Buka kunci grup jika ingin mengundi ulang.';
        } elseif ($hasPlayedMatches) {
            $drawBlockReason = 'Pertandingan telah dimulai atau selesai. Grup tidak dapat diundi ulang.';
        } elseif ($eligibleTeams->count() < 2) {
            $drawBlockReason = 'Minimal dibutuhkan 2 tim sah terverifikasi untuk melakukan pengundian grup.';
        }

        // 2. Kunci Grup: Boleh jika grup sudah dibentuk (>0) & belum ada laga berjalan
        $canToggleGroupLock = $groupsCount > 0 && ! $hasPlayedMatches;
        $lockBlockReason = null;
        if ($groupsCount === 0) {
            $lockBlockReason = 'Belum ada grup yang dibentuk. Silakan lakukan pengundian grup terlebih dahulu.';
        } elseif ($hasPlayedMatches) {
            $lockBlockReason = 'Pertandingan telah dimulai atau selesai. Status kunci grup tidak dapat diubah.';
        }

        // 3. Jadwal Pertandingan Grup: Boleh jika grup WAJIB SUDAH DIKUNCI
        $canGenerateGroupMatches = $event->is_group_locked && $groupsCount > 0;
        $groupMatchesBlockReason = null;
        if (! $event->is_group_locked) {
            $groupMatchesBlockReason = 'Pembagian grup WAJIB dikunci resmi pada sesi Technical Meeting terlebih dahulu sebelum menyusun jadwal pertandingan.';
        } elseif ($groupsCount === 0) {
            $groupMatchesBlockReason = 'Belum ada grup yang dibentuk.';
        }

        // 4. Jadwal Knockout: Boleh jika grup dikunci dan seluruh laga grup sudah completed
        $canGenerateKnockout = $event->is_group_locked && $allMatchesCount > 0 && ! $hasPendingGroupMatches;
        $knockoutBlockReason = null;
        if (! $event->is_group_locked) {
            $knockoutBlockReason = 'Pembagian grup belum dikunci resmi.';
        } elseif ($hasPendingGroupMatches) {
            $knockoutBlockReason = 'Seluruh pertandingan fase grup harus berstatus SELESAI terlebih dahulu agar penentuan juara & runner-up grup final.';
        }

        // 5. Rilis E-Sertifikat: Boleh jika turnamen sudah status completed atau seluruh jadwal selesai
        $canReleaseCertificates = ($event->status === 'completed') || ($allMatchesCount > 0 && $uncompletedMatchesCount === 0);
        $certificatesBlockReason = ! $canReleaseCertificates
            ? 'E-Sertifikat hanya dapat dirilis setelah seluruh rangkaian pertandingan turnamen selesai (status: completed).'
            : null;

        // Penentuan Fase Siklus Turnamen untuk Banner SOP
        $currentPhase = 'registration';
        if ($isRegistrationOpen) {
            $currentPhase = 'registration';
        } elseif ($groupsCount === 0) {
            $currentPhase = 'technical_meeting';
        } elseif (! $event->is_group_locked) {
            $currentPhase = 'group_drawn';
        } elseif ($allMatchesCount === 0) {
            $currentPhase = 'scheduling';
        } elseif ($uncompletedMatchesCount > 0) {
            $currentPhase = 'matchday';
        } else {
            $currentPhase = 'completed';
        }

        return Inertia::render('Admin/Events/Tournament', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'age_category' => $event->age_category,
                'format' => $event->format,
                'match_format' => $event->match_format ?? '11_vs_11',
                'starting_players_count' => (int) ($event->starting_players_count ?? 11),
                'dsp_cutoff_minutes' => (int) ($event->dsp_cutoff_minutes ?? 30),
                'venue_name' => $event->venue_name,
                'status' => $event->status,
                'max_teams' => (int) $event->max_teams,
                'occupied_slots' => $occupiedSlots,
                'is_group_locked' => (bool) $event->is_group_locked,
                'certificates_released' => (bool) ($event->certificates_released || $event->status === 'completed'),
            ],
            'controls' => [
                'current_phase' => $currentPhase,
                'is_registration_open' => $isRegistrationOpen,
                'can_draw_groups' => $canDrawGroups,
                'draw_block_reason' => $drawBlockReason,
                'can_toggle_group_lock' => $canToggleGroupLock,
                'lock_block_reason' => $lockBlockReason,
                'can_generate_group_matches' => $canGenerateGroupMatches,
                'group_matches_block_reason' => $groupMatchesBlockReason,
                'can_generate_knockout' => $canGenerateKnockout,
                'knockout_block_reason' => $knockoutBlockReason,
                'can_release_certificates' => $canReleaseCertificates,
                'certificates_block_reason' => $certificatesBlockReason,
                'uncompleted_matches_count' => $uncompletedMatchesCount,
                'all_matches_count' => $allMatchesCount,
            ],
            'eligibleTeams' => $eligibleTeams,
            'groups' => $groups,
            'matches' => $matches,
            'announcements' => $announcements,
            'leaderboard' => $this->standingsService->getEventLeaderboard($event),
            'stages' => TournamentMatch::STAGES,
            'matchStatuses' => TournamentMatch::STATUSES,
            'eventTypes' => MatchEvent::TYPES,
        ]);
    }

    /**
     * Auto-generate Grup & Drawing Tim.
     */
    public function generateGroups(Request $request, Event $event): RedirectResponse
    {
        $occupiedSlots = $event->occupiedSlotsCount();
        $isRegistrationOpen = $event->status === 'open' && $event->isWithinRegistrationWindow() && $occupiedSlots < $event->max_teams;

        if ($isRegistrationOpen) {
            throw ValidationException::withMessages([
                'group' => "Pendaftaran turnamen masih berstatus OPEN dan kuota belum penuh ({$occupiedSlots}/{$event->max_teams} tim). Pengundian grup baru dapat dilakukan pada sesi Technical Meeting setelah kuota terpenuhi atau pendaftaran resmi ditutup.",
            ]);
        }

        if ($event->is_group_locked) {
            throw ValidationException::withMessages([
                'group' => 'Pembagian grup telah dikunci panitia. Buka kunci grup terlebih dahulu jika ingin mengundi ulang.',
            ]);
        }

        $hasPlayedMatches = $event->matches()->where('status', '!=', 'scheduled')->exists();
        if ($hasPlayedMatches) {
            throw ValidationException::withMessages([
                'group' => 'Pertandingan telah dimulai atau selesai. Grup tidak dapat diundi ulang.',
            ]);
        }

        $validated = $request->validate([
            'group_count' => ['required', 'integer', 'min:1', 'max:16'],
            'only_locked_roster' => ['nullable', 'boolean'],
        ]);

        $eligibleCount = $event->teams()
            ->when($validated['only_locked_roster'] ?? false, fn ($q) => $q->where('status', 'locked_roster'), fn ($q) => $q->whereIn('status', ['active', 'locked_roster']))
            ->count();
        if ($eligibleCount < 2) {
            throw ValidationException::withMessages([
                'group' => 'Jumlah tim sah yang siap diundi belum mencukupi (minimal 2 tim terverifikasi).',
            ]);
        }

        $this->standingsService->generateGroupsAndDraw(
            event: $event,
            groupCount: (int) $validated['group_count'],
            onlyLockedRoster: (bool) ($validated['only_locked_roster'] ?? false),
        );

        return redirect()->back()->with('success', "Berhasil membentuk {$validated['group_count']} grup dan mengundi penempatan tim.");
    }

    /**
     * Kunci / Buka Kunci Hasil Drawing Pembagian Grup.
     */
    public function toggleGroupLock(Event $event): RedirectResponse
    {
        if (! $event->is_group_locked) {
            if ($event->groups()->count() === 0) {
                throw ValidationException::withMessages([
                    'group' => 'Belum ada pembagian grup yang dibentuk. Silakan lakukan pengundian grup terlebih dahulu.',
                ]);
            }
            $emptyGroup = $event->groups()->whereDoesntHave('groupTeams')->first();
            if ($emptyGroup) {
                throw ValidationException::withMessages([
                    'group' => "Grup '{$emptyGroup->name}' masih kosong tanpa tim. Pastikan seluruh grup terisi tim sebelum dikunci.",
                ]);
            }
        } else {
            $hasPlayedMatches = $event->matches()->where('status', '!=', 'scheduled')->exists();
            if ($hasPlayedMatches) {
                throw ValidationException::withMessages([
                    'group' => 'Pertandingan telah dimulai atau selesai. Status kunci pembagian grup tidak dapat dibuka kembali.',
                ]);
            }
        }

        $event->is_group_locked = ! $event->is_group_locked;
        $event->save();

        $msg = $event->is_group_locked
            ? 'Pembagian grup resmi dikunci. Undian otomatis dan penataan grup dinonaktifkan.'
            : 'Kunci pembagian grup dibuka. Pengundian dan penyesuaian tim dapat dilakukan kembali.';

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Pindahkan Tim Antar Grup Secara Manual (Manual Seeding / Penyesuaian Tim Se-Akademi).
     */
    public function moveTeamGroup(Request $request, Event $event): RedirectResponse
    {
        if ($event->is_group_locked) {
            throw ValidationException::withMessages([
                'group' => 'Pembagian grup sedang dikunci. Buka kunci grup terlebih dahulu jika ingin memindahkan tim.',
            ]);
        }

        $validated = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'target_group_id' => ['required', 'exists:event_groups,id'],
        ]);

        $team = Team::where('id', $validated['team_id'])->where('event_id', $event->id)->firstOrFail();
        $targetGroup = EventGroup::where('id', $validated['target_group_id'])->where('event_id', $event->id)->firstOrFail();

        $hasPlayed = TournamentMatch::query()
            ->where('event_id', $event->id)
            ->where(fn ($q) => $q->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id))
            ->where('status', '!=', 'scheduled')
            ->exists();

        if ($hasPlayed) {
            throw ValidationException::withMessages([
                'team_id' => "Tim {$team->name} telah memainkan pertandingan. Posisi grup tidak dapat dipindahkan lagi.",
            ]);
        }

        DB::transaction(function () use ($event, $team, $targetGroup) {
            $existing = EventGroupTeam::query()
                ->where('team_id', $team->id)
                ->whereHas('group', fn ($q) => $q->where('event_id', $event->id))
                ->first();

            if ($existing) {
                $existing->update(['event_group_id' => $targetGroup->id]);
            } else {
                EventGroupTeam::create([
                    'event_group_id' => $targetGroup->id,
                    'team_id' => $team->id,
                ]);
            }

            $this->standingsService->recalculateEventStandings($event);
        });

        return redirect()->back()->with('success', "Tim {$team->name} berhasil dipindahkan ke {$targetGroup->name}.");
    }

    /**
     * Generate jadwal pertandingan fase grup (Round-Robin) otomatis.
     */
    public function generateGroupMatches(Request $request, Event $event): RedirectResponse
    {
        if (! $event->is_group_locked) {
            throw ValidationException::withMessages([
                'matches' => 'Pembagian grup belum dikunci resmi. Lakukan pengundian pada sesi Technical Meeting dan kunci grup terlebih dahulu sebelum menyusun jadwal pertandingan.',
            ]);
        }

        if ($event->groups()->count() === 0) {
            throw ValidationException::withMessages([
                'matches' => 'Belum ada grup yang dibentuk. Silakan lakukan pengundian grup terlebih dahulu.',
            ]);
        }

        $validated = $request->validate([
            'start_time' => ['nullable', 'date'],
            'interval_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'pitches' => ['nullable', 'string', 'max:255'],
        ]);

        $pitchArray = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($validated['pitches'] ?? 'Lapangan A, Lapangan B'))
        )));

        $created = $this->standingsService->generateGroupStageMatches(
            event: $event,
            startTime: $validated['start_time'] ?? null,
            matchIntervalMinutes: (int) ($validated['interval_minutes'] ?? 60),
            pitches: $pitchArray ?: ['Lapangan A'],
        );

        return redirect()->back()->with('success', "Berhasil menjadwalkan {$created->count()} pertandingan fase grup secara otomatis.");
    }

    /**
     * Generate jadwal pertandingan fase gugur / knockout otomatis dari tim juara & runner-up grup.
     */
    public function generateKnockoutMatches(Request $request, Event $event): RedirectResponse
    {
        if (! $event->is_group_locked) {
            throw ValidationException::withMessages([
                'stage' => 'Pembagian grup belum dikunci resmi.',
            ]);
        }

        $hasPendingGroupMatches = $event->matches()->where('stage', 'group')->where('status', '!=', 'completed')->exists();
        if ($hasPendingGroupMatches) {
            throw ValidationException::withMessages([
                'stage' => 'Seluruh pertandingan fase grup harus berstatus SELESAI terlebih dahulu agar penentuan juara dan runner-up grup final.',
            ]);
        }

        $validated = $request->validate([
            'stage' => ['required', 'string', 'in:round_of_16,quarter_final,semi_final,final,third_place'],
            'start_time' => ['nullable', 'date'],
            'interval_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'pitches' => ['nullable', 'string', 'max:255'],
        ]);

        $pitchArray = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($validated['pitches'] ?? 'Lapangan Utama'))
        )));

        $matches = $this->standingsService->generateKnockoutStageMatches(
            event: $event,
            stage: $validated['stage'],
            startTime: $validated['start_time'] ?? null,
            intervalMinutes: (int) ($validated['interval_minutes'] ?? 60),
            pitches: $pitchArray ?: ['Lapangan Utama'],
        );

        $stageLabel = TournamentMatch::stageLabel($validated['stage']);

        return redirect()->back()->with('success', "Berhasil menjadwalkan {$matches->count()} pertandingan babak {$stageLabel} dari tim yang lolos grup.");
    }

    /**
     * Tambah jadwal pertandingan baru (Fase Grup / Fase Gugur).
     */
    public function storeMatch(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => ['required', 'string'],
            'event_group_id' => ['nullable', 'exists:event_groups,id'],
            'home_team_id' => ['nullable', 'exists:teams,id'],
            'away_team_id' => ['nullable', 'exists:teams,id'],
            'kickoff_at' => ['nullable', 'date'],
            'pitch_name' => ['nullable', 'string', 'max:80'],
            'referee_name' => ['nullable', 'string', 'max:120'],
        ]);

        if ($validated['stage'] === 'group' && ! $event->is_group_locked) {
            throw ValidationException::withMessages([
                'stage' => 'Pembagian grup harus dikunci terlebih dahulu sebelum menambahkan pertandingan fase grup.',
            ]);
        }

        $nextMatchNumber = (int) $event->matches()->max('match_number') + 1;

        TournamentMatch::create([
            'event_id' => $event->id,
            'event_group_id' => $validated['event_group_id'] ?? null,
            'stage' => $validated['stage'],
            'match_number' => $nextMatchNumber,
            'home_team_id' => $validated['home_team_id'] ?? null,
            'away_team_id' => $validated['away_team_id'] ?? null,
            'kickoff_at' => $validated['kickoff_at'] ?? null,
            'pitch_name' => $validated['pitch_name'] ?? 'Lapangan Utama',
            'referee_name' => $validated['referee_name'] ?? null,
            'status' => 'scheduled',
        ]);

        return redirect()->back()->with('success', "Pertandingan #{$nextMatchNumber} berhasil ditambahkan ke jadwal.");
    }

    /**
     * Simpan Hasil Skor Pertandingan, Kejadian Gol/Kartu, & Hitung Ulang Klasemen Otomatis.
     */
    public function updateMatchResult(Request $request, TournamentMatch $match): RedirectResponse
    {
        if (! $match->home_team_id || ! $match->away_team_id) {
            throw ValidationException::withMessages([
                'status' => 'Pertandingan belum memiliki tim bertanding yang lengkap (home & away).',
            ]);
        }

        if ($match->event?->status === 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Turnamen telah berstatus selesai. Hasil pertandingan tidak dapat diubah lagi.',
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:scheduled,ongoing,completed,postponed'],
            'home_score' => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:99'],
            'home_penalty_score' => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_penalty_score' => ['nullable', 'integer', 'min:0', 'max:99'],
            'kickoff_at' => ['nullable', 'date'],
            'pitch_name' => ['nullable', 'string', 'max:80'],
            'referee_name' => ['nullable', 'string', 'max:120'],
            'match_notes' => ['nullable', 'string', 'max:1000'],
            'events' => ['nullable', 'array'],
            'events.*.team_id' => ['required', 'exists:teams,id'],
            'events.*.player_id' => ['nullable', 'exists:players,id'],
            'events.*.event_type' => ['required', 'in:goal,penalty_goal,own_goal,yellow_card,second_yellow_card,red_card'],
            'events.*.minute' => ['nullable', 'integer', 'min:1', 'max:150'],
            'events.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($match, $validated, $request) {
            $match->update([
                'status' => $validated['status'],
                'home_score' => $validated['status'] === 'completed' ? (int) ($validated['home_score'] ?? 0) : ($validated['home_score'] ?? null),
                'away_score' => $validated['status'] === 'completed' ? (int) ($validated['away_score'] ?? 0) : ($validated['away_score'] ?? null),
                'home_penalty_score' => $validated['home_penalty_score'] ?? null,
                'away_penalty_score' => $validated['away_penalty_score'] ?? null,
                'kickoff_at' => $validated['kickoff_at'] ?? $match->kickoff_at,
                'pitch_name' => $validated['pitch_name'] ?? $match->pitch_name,
                'referee_name' => $validated['referee_name'] ?? $match->referee_name,
                'match_notes' => $validated['match_notes'] ?? null,
            ]);

            if ($request->has('events')) {
                $match->matchEvents()->delete();
                foreach ($validated['events'] ?? [] as $ev) {
                    MatchEvent::create([
                        'match_id' => $match->id,
                        'team_id' => $ev['team_id'],
                        'player_id' => $ev['player_id'] ?? null,
                        'event_type' => $ev['event_type'],
                        'minute' => $ev['minute'] ?? null,
                        'notes' => $ev['notes'] ?? null,
                    ]);
                }
            }

            $this->standingsService->recalculateEventStandings($match->event);
        });

        return redirect()->back()->with('success', "Hasil pertandingan #{$match->match_number} dan klasemen grup berhasil diperbarui.");
    }

    /**
     * Reschedule Jadwal Pertandingan (Waktu Kick-off, Lapangan, Wasit, Catatan).
     */
    public function rescheduleMatch(Request $request, TournamentMatch $match): RedirectResponse
    {
        $validated = $request->validate([
            'kickoff_at' => ['required', 'date'],
            'pitch_name' => ['nullable', 'string', 'max:80'],
            'referee_name' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'in:scheduled,ongoing,completed,postponed'],
            'match_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $match->update([
            'kickoff_at' => $validated['kickoff_at'],
            'pitch_name' => $validated['pitch_name'] ?? $match->pitch_name,
            'referee_name' => $validated['referee_name'] ?? $match->referee_name,
            'status' => $validated['status'] ?? $match->status,
            'match_notes' => $validated['match_notes'] ?? $match->match_notes,
        ]);

        return redirect()->back()->with('success', "Jadwal pertandingan #{$match->match_number} berhasil diperbarui.");
    }

    /**
     * Hapus jadwal pertandingan.
     */
    public function destroyMatch(TournamentMatch $match): RedirectResponse
    {
        $event = $match->event;
        $num = $match->match_number;
        $match->delete();

        if ($event) {
            $this->standingsService->recalculateEventStandings($event);
        }

        return redirect()->back()->with('success', "Pertandingan #{$num} telah dihapus.");
    }

    /**
     * Fase 6.1: Publikasikan Pengumuman / Undangan Technical Meeting.
     */
    public function storeAnnouncement(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:3000'],
            'meeting_link' => ['nullable', 'url', 'max:255'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        EventAnnouncement::create([
            'event_id' => $event->id,
            'title' => trim($validated['title']),
            'content' => trim($validated['content']),
            'meeting_link' => $validated['meeting_link'] ?? null,
            'is_pinned' => (bool) ($validated['is_pinned'] ?? false),
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->back()->with('success', 'Pengumuman resmi berhasil dipublikasikan ke Dashboard Manajer Tim.');
    }

    public function destroyAnnouncement(EventAnnouncement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()->back()->with('success', 'Pengumuman berhasil dihapus.');
    }

    /**
     * Fase 6.3: Set Predikat Juara Tim untuk E-Certificate Pemain (misal: Juara 1, Juara 2, Peserta Resmi).
     */
    public function updateTeamAchievement(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'achievement_title' => ['nullable', 'string', 'max:100'],
        ]);

        $team->update([
            'achievement_title' => $validated['achievement_title'] ?: null,
        ]);

        return redirect()->back()->with('success', "Predikat piagam untuk tim {$team->name} berhasil diperbarui.");
    }

    /**
     * Fase 6.3: Rilis / Kunci Akses Unduh E-Certificate Pemain untuk Manajer.
     */
    public function toggleCertificateRelease(Event $event): RedirectResponse
    {
        if (! $event->certificates_released) {
            $hasUncompleted = $event->matches()->where('status', '!=', 'completed')->exists();
            if ($event->status !== 'completed' && $hasUncompleted) {
                throw ValidationException::withMessages([
                    'certificates' => 'E-Sertifikat belum dapat dirilis. Seluruh rangkaian pertandingan turnamen harus telah berstatus SELESAI (Completed) terlebih dahulu.',
                ]);
            }
        }

        $event->certificates_released = ! $event->certificates_released;
        $event->save();

        $msg = $event->certificates_released
            ? 'E-Sertifikat digital resmi dirilis. Seluruh manajer tim sekarang dapat mengunduh piagam penghargaan pemain.'
            : 'Akses unduh E-Sertifikat digital ditutup sementara untuk manajer tim.';

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Fase 6.4: Halaman Web QR Scanner Lapangan (Matchday Live Scanner).
     */
    public function qrScanner(): Response
    {
        return Inertia::render('Admin/Screening/QrScanner');
    }

    /**
     * Fase 6.4: Endpoint JSON Cepat untuk Lookup Kode Akreditasi Pemain atau Ofisial saat Matchday.
     */
    public function qrLookup(Request $request): JsonResponse
    {
        $raw = trim((string) $request->input('code', ''));
        if ($raw === '') {
            return response()->json([
                'found' => false,
                'message' => 'Kode QR atau Kode Akreditasi wajib diisi.',
            ], 422);
        }

        // Jika input berupa URL penuh (misal hasil scan QR: https://.../verify/player/YT-E01-...)
        if (str_contains($raw, '/verify/official/')) {
            $parts = explode('/verify/official/', $raw);
            $raw = trim( explode('?', end($parts))[0], '/');
        } elseif (str_contains($raw, '/verify/player/')) {
            $parts = explode('/verify/player/', $raw);
            $raw = trim(explode('?', end($parts))[0], '/');
        }

        $code = strtoupper($raw);

        // Cek apakah kode Ofisial (OFF-0001)
        if (str_starts_with($code, 'OFF-')) {
            $officialId = (int) substr($code, 4);
            $official = TeamOfficial::with(['team.event'])->find($officialId);

            if (! $official || ! $official->team) {
                return response()->json([
                    'found' => false,
                    'code' => $code,
                    'message' => 'Data ofisial tidak ditemukan dalam sistem akreditasi.',
                ]);
            }

            $isEligible = in_array($official->team->status, ['active', 'locked_roster'], true)
                && $official->validation_status !== 'rejected';

            return response()->json([
                'found' => true,
                'entity_type' => 'official',
                'is_eligible' => $isEligible,
                'code' => $code,
                'full_name' => $official->full_name,
                'role' => $official->role,
                'license_number' => $official->license_number ?: '-',
                'team_name' => $official->team->name,
                'event_name' => $official->team->event?->name,
                'age_category' => $official->team->event?->age_category,
                'photo_url' => $official->photo_path
                    ? route('public.official.verify.photo', ['code' => $code])
                    : null,
                'status_label' => $isEligible ? 'OFISIAL SAH & LAYAK DAMPING BENCH' : 'OFISIAL TIDAK AKTIF / DITOLAK',
            ]);
        }

        // Cek kode Pemain (YT-E01-PL...)
        $player = Player::with(['team.event'])
            ->where('accreditation_code', $code)
            ->first();

        if (! $player || ! $player->team) {
            return response()->json([
                'found' => false,
                'code' => $code,
                'message' => 'Kode akreditasi pemain tidak ditemukan dalam database resmi.',
            ]);
        }

        $disciplinary = $this->standingsService->checkPlayerDisciplinaryStatus($player);
        $isEligible = $player->screening_status === 'passed'
            && $player->team->status !== 'disqualified'
            && ! $disciplinary['is_suspended'];

        return response()->json([
            'found' => true,
            'entity_type' => 'player',
            'is_eligible' => $isEligible,
            'code' => $player->accreditation_code,
            'full_name' => $player->full_name,
            'jersey_number' => $player->jersey_number,
            'position' => $player->position,
            'birth_date' => $player->birth_date?->translatedFormat('d F Y'),
            'team_name' => $player->team->name,
            'event_name' => $player->team->event?->name,
            'age_category' => $player->team->event?->age_category,
            'photo_url' => $player->photo_path
                ? route('public.player.verify.photo', ['code' => $player->accreditation_code])
                : null,
            'is_suspended' => $disciplinary['is_suspended'],
            'suspension_reason' => $disciplinary['reason'],
            'yellow_cards' => $disciplinary['yellow_count'],
            'red_cards' => $disciplinary['red_count'],
            'status_label' => ! $isEligible
                ? ($disciplinary['is_suspended'] ? 'LARANGAN BERMAIN (AKUMULASI KARTU)' : 'TIDAK SAH / DISKUALIFIKASI')
                : 'PEMAIN SAH & LAYAK TANDING',
        ]);
    }
}

