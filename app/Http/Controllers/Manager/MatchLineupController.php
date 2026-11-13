<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesActiveTeam;
use App\Http\Requests\Manager\SaveMatchLineupRequest;
use App\Models\MatchLineup;
use App\Models\Player;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Services\CertificateService;
use App\Services\StandingsCalculatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MatchLineupController extends Controller
{
    use ResolvesActiveTeam;

    public function __construct(
        protected StandingsCalculatorService $standingsService,
        protected CertificateService $certificateService,
    ) {}

    /**
     * Halaman Jadwal Pertandingan Tim, Klasemen Grup & Pengajuan Daftar Susunan Pemain (DSP).
     */
    public function index(Request $request): Response
    {
        $ctx = $this->resolveManagerContext($request);
        /** @var Team|null $team */
        $team = $ctx['activeTeam'];

        $matchesPayload = [];
        $eligiblePlayers = [];
        $standingsGroups = [];

        if ($team) {
            $team->loadMissing(['event.groups.groupTeams.team']);

            $passedPlayers = $team->players()
                ->where('screening_status', 'passed')
                ->orderBy('jersey_number')
                ->get();

            $matches = TournamentMatch::query()
                ->with(['homeTeam', 'awayTeam', 'group', 'lineups', 'matchEvents.player'])
                ->where('event_id', $team->event_id)
                ->where(function ($q) use ($team) {
                    $q->where('home_team_id', $team->id)
                        ->orWhere('away_team_id', $team->id);
                })
                ->orderBy('match_number')
                ->orderBy('kickoff_at')
                ->get();

            $matchesPayload = $matches->map(function (TournamentMatch $m) use ($team, $passedPlayers) {
                $teamLineups = $m->lineups->where('team_id', $team->id);
                $event = $team->event;
                $cutoffMinutes = (int) ($event?->dsp_cutoff_minutes ?? 30);
                $cutoffAt = $m->kickoff_at ? $m->kickoff_at->copy()->subMinutes($cutoffMinutes) : null;
                $isPassedCutoff = $cutoffAt ? now()->gt($cutoffAt) : false;
                $isMatchLocked = in_array($m->status, ['ongoing', 'completed', 'cancelled'], true) || $isPassedCutoff;

                $lockReason = null;
                if (in_array($m->status, ['ongoing', 'completed'], true)) {
                    $lockReason = 'Pertandingan sedang berlangsung atau telah selesai.';
                } elseif ($isPassedCutoff) {
                    $lockReason = "Batas waktu pengajuan DSP telah ditutup ({$cutoffMinutes} menit sebelum kick-off).";
                }

                $playersWithDisciplinary = $passedPlayers->map(function (Player $p) use ($m) {
                    $disc = $this->standingsService->checkPlayerDisciplinaryStatus($p, $m);

                    return [
                        'id' => $p->id,
                        'full_name' => $p->full_name,
                        'jersey_number' => $p->jersey_number,
                        'position' => $p->position,
                        'accreditation_code' => $p->accreditation_code,
                        'is_suspended' => $disc['is_suspended'],
                        'suspension_reason' => $disc['reason'],
                        'yellow_cards' => $disc['yellow_count'],
                        'red_cards' => $disc['red_count'],
                    ];
                })->values();

                return [
                    'id' => $m->id,
                    'match_number' => $m->match_number,
                    'stage' => $m->stage,
                    'stage_label' => TournamentMatch::stageLabel($m->stage),
                    'group_name' => $m->group?->name,
                    'is_home' => (int) $m->home_team_id === (int) $team->id,
                    'home_team_name' => $m->homeTeam?->name ?? 'TBD',
                    'away_team_name' => $m->awayTeam?->name ?? 'TBD',
                    'opponent_name' => (int) $m->home_team_id === (int) $team->id
                        ? ($m->awayTeam?->name ?? 'TBD')
                        : ($m->homeTeam?->name ?? 'TBD'),
                    'kickoff_formatted' => $m->kickoff_at?->translatedFormat('d M Y, H:i') . ($m->kickoff_at ? ' WIB' : ''),
                    'pitch_name' => $m->pitch_name ?: 'Lapangan Utama',
                    'status' => $m->status,
                    'status_label' => TournamentMatch::STATUSES[$m->status] ?? $m->status,
                    'home_score' => $m->home_score,
                    'away_score' => $m->away_score,
                    'lineup_submitted' => $teamLineups->isNotEmpty(),
                    'is_dsp_locked' => $isMatchLocked,
                    'dsp_lock_reason' => $lockReason,
                    'cutoff_at_formatted' => $cutoffAt ? $cutoffAt->translatedFormat('H:i') . ' WIB' : null,
                    'starting_player_ids' => $teamLineups->where('is_starting', true)->pluck('player_id')->values(),
                    'substitute_player_ids' => $teamLineups->where('is_starting', false)->pluck('player_id')->values(),
                    'captain_player_id' => $teamLineups->firstWhere('is_captain', true)?->player_id,
                    'players_availability' => $playersWithDisciplinary,
                ];
            })->values();

            $eligiblePlayers = $passedPlayers->map(fn (Player $p) => [
                'id' => $p->id,
                'full_name' => $p->full_name,
                'jersey_number' => $p->jersey_number,
                'position' => $p->position,
                'accreditation_code' => $p->accreditation_code,
            ])->values();

            if ($team->event) {
                $standingsGroups = $team->event->groups->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'standings' => $g->groupTeams->map(fn ($gt) => [
                        'team_id' => $gt->team_id,
                        'team_name' => $gt->team?->name ?? '-',
                        'is_my_team' => (int) $gt->team_id === (int) $team->id,
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
            }
        }

        return Inertia::render('Manager/Matches', [
            'team' => $ctx['teamPayload'],
            'teamsList' => $ctx['teamsList'],
            'matches' => $matchesPayload,
            'eligiblePlayers' => $eligiblePlayers,
            'standingsGroups' => $standingsGroups,
            'eventFormat' => [
                'match_format' => $team?->event?->match_format ?? '11_vs_11',
                'starting_players_count' => (int) ($team?->event?->starting_players_count ?? 11),
                'dsp_cutoff_minutes' => (int) ($team?->event?->dsp_cutoff_minutes ?? 30),
                'certificates_released' => (bool) ($team?->event?->certificates_released || $team?->event?->status === 'completed'),
            ],
        ]);
    }

    /**
     * Simpan Daftar Susunan Pemain (Digital Match Lineup / DSP) oleh Manajer Tim.
     */
    public function saveLineup(SaveMatchLineupRequest $request, TournamentMatch $match): RedirectResponse
    {
        $ctx = $this->resolveManagerContext($request);
        /** @var Team|null $team */
        $team = $ctx['activeTeam'];

        if (! $team || ((int) $match->home_team_id !== (int) $team->id && (int) $match->away_team_id !== (int) $team->id)) {
            abort(403, 'Pertandingan ini bukan milik tim Anda.');
        }

        $event = $team->event;
        if (! $event || ! $event->canRunFieldOperations()) {
            throw ValidationException::withMessages([
                'lineup' => 'Daftar Susunan Pemain (DSP) hanya dapat diajukan setelah pendaftaran event ditutup atau saat turnamen sedang berlangsung.',
            ]);
        }

        if (in_array($match->status, ['ongoing', 'completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'lineup' => 'Pertandingan sedang berlangsung atau telah selesai. Daftar Susunan Pemain (DSP) tidak dapat diubah lagi.',
            ]);
        }

        $cutoffMinutes = (int) ($event?->dsp_cutoff_minutes ?? 30);
        if ($match->kickoff_at && now()->gt($match->kickoff_at->copy()->subMinutes($cutoffMinutes))) {
            throw ValidationException::withMessages([
                'lineup' => "Batas pengajuan DSP telah ditutup ({$cutoffMinutes} menit sebelum kick-off: {$match->kickoff_at->copy()->subMinutes($cutoffMinutes)->format('H:i')} WIB). Hubungi Match Commissioner jika ada pergantian darurat sebelum laga.",
            ]);
        }

        $validated = $request->validated();

        $startingIds = array_values(array_unique(array_map('intval', $validated['starting_player_ids'])));
        $subIds = array_values(array_diff(
            array_unique(array_map('intval', $validated['substitute_player_ids'] ?? [])),
            $startingIds
        ));
        $allSelectedIds = array_merge($startingIds, $subIds);

        $players = $team->players()
            ->where('screening_status', 'passed')
            ->whereIn('id', $allSelectedIds)
            ->get()
            ->keyBy('id');

        if ($players->count() !== count($allSelectedIds)) {
            throw ValidationException::withMessages([
                'lineup' => 'Hanya pemain dari tim Anda yang telah dinyatakan Sah (Lolos Screening) yang dapat dimasukkan ke DSP.',
            ]);
        }

        // Validasi format pertandingan & kuota starter wajib
        $requiredStarting = (int) ($event?->starting_players_count ?? 11);
        $eligiblePassedCount = $team->players()->where('screening_status', 'passed')->count();
        $expectedStartingCount = min($requiredStarting, $eligiblePassedCount);

        if (count($startingIds) !== $expectedStartingCount) {
            throw ValidationException::withMessages([
                'starting_player_ids' => "Format pertandingan mewajibkan tepat {$expectedStartingCount} pemain inti (Starting Lineup). Saat ini Anda memilih " . count($startingIds) . " pemain.",
            ]);
        }

        // Validasi wajib minimal 1 kiper di starting lineup jika tim memiliki kiper yang sah
        $startingGkCount = $players->whereIn('id', $startingIds)
            ->filter(fn ($p) => in_array($p->position, ['Penjaga Gawang', 'GK'], true))
            ->count();
        $teamGkCount = $team->players()->where('screening_status', 'passed')
            ->whereIn('position', ['Penjaga Gawang', 'GK'])
            ->count();

        if ($teamGkCount > 0 && $expectedStartingCount >= 3 && $startingGkCount < 1) {
            throw ValidationException::withMessages([
                'starting_player_ids' => 'Starting lineup wajib menyertakan minimal 1 Penjaga Gawang (GK).',
            ]);
        }

        // Validasi suspensi kartu disiplin
        foreach ($players as $player) {
            $disc = $this->standingsService->checkPlayerDisciplinaryStatus($player, $match);
            if ($disc['is_suspended']) {
                throw ValidationException::withMessages([
                    'lineup' => "Pemain {$player->full_name} (#{$player->jersey_number}) tidak dapat diturunkan: {$disc['reason']}",
                ]);
            }
        }

        $captainId = isset($validated['captain_player_id']) ? (int) $validated['captain_player_id'] : ($startingIds[0] ?? null);

        DB::transaction(function () use ($match, $team, $startingIds, $subIds, $players, $captainId) {
            MatchLineup::query()
                ->where('match_id', $match->id)
                ->where('team_id', $team->id)
                ->delete();

            foreach ($startingIds as $pid) {
                /** @var Player $p */
                $p = $players->get($pid);
                MatchLineup::create([
                    'match_id' => $match->id,
                    'team_id' => $team->id,
                    'player_id' => $p->id,
                    'is_starting' => true,
                    'jersey_number_snapshot' => $p->jersey_number,
                    'position_snapshot' => $p->position,
                    'is_captain' => $p->id === $captainId,
                ]);
            }

            foreach ($subIds as $pid) {
                /** @var Player $p */
                $p = $players->get($pid);
                MatchLineup::create([
                    'match_id' => $match->id,
                    'team_id' => $team->id,
                    'player_id' => $p->id,
                    'is_starting' => false,
                    'jersey_number_snapshot' => $p->jersey_number,
                    'position_snapshot' => $p->position,
                    'is_captain' => false,
                ]);
            }
        });

        return redirect()->back()->with('success', "Daftar Susunan Pemain (DSP) Pertandingan #{$match->match_number} berhasil disimpan.");
    }

    /**
     * Unduh PDF Daftar Susunan Pemain (DSP) untuk diserahkan ke meja Pengawas Pertandingan.
     */
    public function downloadLineupPdf(Request $request, TournamentMatch $match)
    {
        $ctx = $this->resolveManagerContext($request);
        /** @var Team|null $team */
        $team = $ctx['activeTeam'];

        if (! $team || ((int) $match->home_team_id !== (int) $team->id && (int) $match->away_team_id !== (int) $team->id)) {
            abort(403, 'Pertandingan ini bukan milik tim Anda.');
        }

        $pdf = $this->certificateService->generateMatchLineupPdf($match, $team->id);
        $filename = 'DSP-Match-' . $match->match_number . '-' . $team->slug . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Fase 6.3: Unduh PDF E-Certificate / Piagam Digital Pemain.
     */
    public function downloadPlayerCertificate(Request $request, Player $player)
    {
        $user = $request->user();
        $isOwner = $user && (int) $player->team?->user_id === (int) $user->id;
        $isAdmin = $user && $user->can('teams.view_all');

        if (! $isOwner && ! $isAdmin) {
            abort(403, 'Anda tidak memiliki akses ke sertifikat pemain ini.');
        }

        if ($player->screening_status !== 'passed') {
            abort(403, 'Sertifikat hanya tersedia bagi pemain yang telah lolos verifikasi akreditasi.');
        }

        $event = $player->team?->event;
        $isReleased = $event && ($event->certificates_released || $event->status === 'completed');

        if (! $isAdmin && ! $isReleased) {
            abort(403, 'Sertifikat elektronik belum dirilis oleh panitia penyelenggara. Sertifikat akan dapat diunduh setelah turnamen selesai atau dirilis resmi oleh panitia.');
        }

        $pdf = $this->certificateService->generatePlayerCertificatePdf($player);
        $filename = 'E-Certificate-' . str($player->full_name)->slug() . '.pdf';

        return $pdf->download($filename);
    }
}

