<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventGroupTeam;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Team;
use App\Models\TournamentMatch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StandingsCalculatorService
{
    /**
     * Menghasilkan grup dan menempatkan tim berstatus locked_roster / active secara merata (Auto-Draw).
     */
    public function generateGroupsAndDraw(Event $event, int $groupCount = 2, bool $onlyLockedRoster = false): Collection
    {
        $groupCount = max(1, min(16, $groupCount));

        return DB::transaction(function () use ($event, $groupCount, $onlyLockedRoster) {
            // Hapus grup lama jika belum ada pertandingan selesai
            $event->groups()->delete();

            $teamsQuery = $event->teams();
            if ($onlyLockedRoster) {
                $teamsQuery->where('status', 'locked_roster');
            } else {
                $teamsQuery->whereIn('status', ['locked_roster', 'active']);
            }

            $teams = $teamsQuery->inRandomOrder()->get()->values();

            $groups = collect();
            $alphabet = range('A', 'Z');

            for ($i = 0; $i < $groupCount; $i++) {
                $letter = $alphabet[$i] ?? (string) ($i + 1);
                $groups->push(EventGroup::create([
                    'event_id' => $event->id,
                    'name' => "Grup {$letter}",
                    'sort_order' => $i + 1,
                ]));
            }

            foreach ($teams as $idx => $team) {
                /** @var EventGroup $targetGroup */
                $targetGroup = $groups[$idx % $groupCount];
                EventGroupTeam::create([
                    'event_group_id' => $targetGroup->id,
                    'team_id' => $team->id,
                ]);
            }

            return $event->groups()->with('groupTeams.team')->get();
        });
    }

    /**
     * Menghasilkan jadwal pertandingan Round-Robin otomatis untuk seluruh grup di sebuah event.
     */
    public function generateGroupStageMatches(
        Event $event,
        ?string $startTime = null,
        int $matchIntervalMinutes = 60,
        array $pitches = ['Lapangan A', 'Lapangan B']
    ): Collection {
        return DB::transaction(function () use ($event, $startTime, $matchIntervalMinutes, $pitches) {
            // Hapus jadwal grup yang masih berstatus scheduled
            $event->matches()
                ->where('stage', 'group')
                ->where('status', 'scheduled')
                ->delete();

            $groups = $event->groups()->with('groupTeams')->get();
            $baseKickoff = $startTime
                ? Carbon::parse($startTime)
                : ($event->match_start_date ? Carbon::parse($event->match_start_date->format('Y-m-d') . ' 08:00:00') : now()->addDays(1)->setTime(8, 0));

            $nextMatchNumber = (int) $event->matches()->max('match_number') + 1;
            $slotIndex = 0;
            $pitchList = ! empty($pitches) ? array_values($pitches) : ['Lapangan Utama'];
            $pitchCount = count($pitchList);
            $created = collect();

            foreach ($groups as $group) {
                $teamIds = $group->groupTeams->pluck('team_id')->values()->all();
                $count = count($teamIds);

                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $pitchIdx = $slotIndex % $pitchCount;
                        $timeStep = intdiv($slotIndex, $pitchCount);
                        $kickoff = $baseKickoff->copy()->addMinutes($timeStep * $matchIntervalMinutes);

                        $match = TournamentMatch::create([
                            'event_id' => $event->id,
                            'event_group_id' => $group->id,
                            'stage' => 'group',
                            'match_number' => $nextMatchNumber++,
                            'home_team_id' => $teamIds[$i],
                            'away_team_id' => $teamIds[$j],
                            'kickoff_at' => $kickoff,
                            'pitch_name' => $pitchList[$pitchIdx],
                            'status' => 'scheduled',
                        ]);

                        $created->push($match);
                        $slotIndex++;
                    }
                }
            }

            return $created;
        });
    }

    /**
     * Menghasilkan jadwal pertandingan Fase Gugur / Knockout Stage (Semi Final, Quarter Final, Final)
     * berdasarkan tim yang lolos (peringkat 1 & 2 dari tiap grup) secara otomatis.
     */
    public function generateKnockoutStageMatches(
        Event $event,
        string $stage = 'semi_final',
        ?string $startTime = null,
        int $intervalMinutes = 60,
        array $pitches = ['Lapangan Utama']
    ): Collection {
        return DB::transaction(function () use ($event, $stage, $startTime, $intervalMinutes, $pitches) {
            $this->recalculateEventStandings($event);

            $groups = $event->groups()->with(['groupTeams' => function ($q) {
                $q->orderByDesc('points')
                    ->orderByDesc('goal_difference')
                    ->orderByDesc('goals_for')
                    ->orderBy('yellow_cards')
                    ->orderBy('red_cards');
            }])->get();

            if ($groups->isEmpty()) {
                throw new \RuntimeException('Belum ada grup yang dibentuk pada kompetisi ini.');
            }

            // Hapus pertandingan fase gugur untuk stage ini yang masih berstatus scheduled
            $event->matches()
                ->where('stage', $stage)
                ->where('status', 'scheduled')
                ->delete();

            $baseKickoff = $startTime
                ? Carbon::parse($startTime)
                : now()->addDays(1)->setTime(9, 0);

            $pitchList = ! empty($pitches) ? array_values($pitches) : ['Lapangan Utama'];
            $pitchCount = count($pitchList);
            $nextMatchNumber = (int) $event->matches()->max('match_number') + 1;
            $created = collect();
            $slotIndex = 0;
            $groupCount = $groups->count();
            $pairings = [];

            if ($groupCount === 2 && in_array($stage, ['semi_final', 'quarter_final'], true)) {
                $gA = $groups[0];
                $gB = $groups[1];
                $tA1 = $gA->groupTeams->get(0)?->team_id;
                $tA2 = $gA->groupTeams->get(1)?->team_id;
                $tB1 = $gB->groupTeams->get(0)?->team_id;
                $tB2 = $gB->groupTeams->get(1)?->team_id;

                if ($tA1 && $tB2) $pairings[] = ['home' => $tA1, 'away' => $tB2];
                if ($tB1 && $tA2) $pairings[] = ['home' => $tB1, 'away' => $tA2];
            } elseif ($groupCount >= 4 && $stage === 'quarter_final') {
                $gA = $groups[0];
                $gB = $groups[1];
                $gC = $groups[2];
                $gD = $groups[3];

                $tA1 = $gA->groupTeams->get(0)?->team_id;
                $tA2 = $gA->groupTeams->get(1)?->team_id;
                $tB1 = $gB->groupTeams->get(0)?->team_id;
                $tB2 = $gB->groupTeams->get(1)?->team_id;
                $tC1 = $gC->groupTeams->get(0)?->team_id;
                $tC2 = $gC->groupTeams->get(1)?->team_id;
                $tD1 = $gD->groupTeams->get(0)?->team_id;
                $tD2 = $gD->groupTeams->get(1)?->team_id;

                if ($tA1 && $tB2) $pairings[] = ['home' => $tA1, 'away' => $tB2];
                if ($tC1 && $tD2) $pairings[] = ['home' => $tC1, 'away' => $tD2];
                if ($tB1 && $tA2) $pairings[] = ['home' => $tB1, 'away' => $tA2];
                if ($tD1 && $tC2) $pairings[] = ['home' => $tD1, 'away' => $tC2];
            } else {
                // Pasangkan tim peringkat 1 dan 2 lintas grup
                $winners = [];
                $runnersUp = [];
                foreach ($groups as $g) {
                    if ($w = $g->groupTeams->get(0)?->team_id) $winners[] = $w;
                    if ($r = $g->groupTeams->get(1)?->team_id) $runnersUp[] = $r;
                }

                $pairCount = min(count($winners), count($runnersUp));
                for ($k = 0; $k < $pairCount; $k++) {
                    $pairings[] = [
                        'home' => $winners[$k],
                        'away' => $runnersUp[($k + 1) % count($runnersUp)],
                    ];
                }
            }

            foreach ($pairings as $pair) {
                if (! $pair['home'] || ! $pair['away']) continue;
                $pitchIdx = $slotIndex % $pitchCount;
                $timeStep = intdiv($slotIndex, $pitchCount);
                $kickoff = $baseKickoff->copy()->addMinutes($timeStep * $intervalMinutes);

                $match = TournamentMatch::create([
                    'event_id' => $event->id,
                    'event_group_id' => null,
                    'stage' => $stage,
                    'match_number' => $nextMatchNumber++,
                    'home_team_id' => $pair['home'],
                    'away_team_id' => $pair['away'],
                    'kickoff_at' => $kickoff,
                    'pitch_name' => $pitchList[$pitchIdx],
                    'status' => 'scheduled',
                ]);
                $created->push($match);
                $slotIndex++;
            }

            return $created;
        });
    }

    /**
     * Menghitung ulang seluruh statistik klasemen grup (Poin, Selisih Gol, Gol Memasukkan, Fair Play Kartu).
     */
    public function recalculateEventStandings(Event $event): void
    {
        DB::transaction(function () use ($event) {
            $groups = $event->groups()->with('groupTeams')->get();
            $completedGroupMatches = $event->matches()
                ->with('matchEvents')
                ->whereIn('stage', ['group', 'group_stage'])
                ->where('status', 'completed')
                ->get();

            foreach ($groups as $group) {
                $groupMatches = $completedGroupMatches->where('event_group_id', $group->id);

                foreach ($group->groupTeams as $entry) {
                    $teamId = (int) $entry->team_id;
                    $played = 0;
                    $won = 0;
                    $drawn = 0;
                    $lost = 0;
                    $gf = 0;
                    $ga = 0;
                    $yellow = 0;
                    $red = 0;

                    foreach ($groupMatches as $match) {
                        $isHome = (int) $match->home_team_id === $teamId;
                        $isAway = (int) $match->away_team_id === $teamId;

                        if (! $isHome && ! $isAway) {
                            continue;
                        }

                        $played++;
                        $teamGoals = $isHome ? (int) $match->home_score : (int) $match->away_score;
                        $oppGoals = $isHome ? (int) $match->away_score : (int) $match->home_score;

                        $gf += $teamGoals;
                        $ga += $oppGoals;

                        if ($teamGoals > $oppGoals) {
                            $won++;
                        } elseif ($teamGoals === $oppGoals) {
                            $drawn++;
                        } else {
                            $lost++;
                        }

                        foreach ($match->matchEvents as $ev) {
                            if ((int) $ev->team_id !== $teamId) {
                                continue;
                            }
                            if ($ev->event_type === 'yellow_card') {
                                $yellow++;
                            } elseif (in_array($ev->event_type, ['second_yellow_card', 'red_card'], true)) {
                                $red++;
                            }
                        }
                    }

                    $entry->update([
                        'played' => $played,
                        'won' => $won,
                        'drawn' => $drawn,
                        'lost' => $lost,
                        'goals_for' => $gf,
                        'goals_against' => $ga,
                        'goal_difference' => $gf - $ga,
                        'points' => ($won * 3) + $drawn,
                        'yellow_cards' => $yellow,
                        'red_cards' => $red,
                    ]);
                }
            }
        });
    }

    /**
     * Mengecek apakah seorang pemain terkena larangan bermain (suspensi kartu merah / akumulasi 2 kartu kuning)
     * sebelum pertandingan tertentu dimulai.
     *
     * @return array{is_suspended: bool, reason: string|null, yellow_count: int, red_count: int}
     */
    public function checkPlayerDisciplinaryStatus(Player $player, ?TournamentMatch $targetMatch = null): array
    {
        $completedMatchIdsQuery = TournamentMatch::query()
            ->where('event_id', $player->event_id)
            ->where('status', 'completed');

        if ($targetMatch) {
            $completedMatchIdsQuery->where('id', '!=', $targetMatch->id)
                ->where(function ($q) use ($targetMatch) {
                    $q->where('match_number', '<', $targetMatch->match_number)
                        ->orWhereNull('match_number');
                });
        }

        $completedMatchIds = $completedMatchIdsQuery->pluck('id');

        if ($completedMatchIds->isEmpty()) {
            return [
                'is_suspended' => false,
                'reason' => null,
                'yellow_count' => 0,
                'red_count' => 0,
            ];
        }

        $events = MatchEvent::query()
            ->whereIn('match_id', $completedMatchIds)
            ->where('player_id', $player->id)
            ->whereIn('event_type', ['yellow_card', 'second_yellow_card', 'red_card'])
            ->get();

        $yellowCount = $events->where('event_type', 'yellow_card')->count();
        $redCount = $events->whereIn('event_type', ['second_yellow_card', 'red_card'])->count();

        // Cek pertandingan terakhir tim yang sudah selesai: jika pemain mendapat kartu merah di laga sebelumnya, ia disuspensi
        $lastTeamMatch = TournamentMatch::query()
            ->where('event_id', $player->event_id)
            ->where('status', 'completed')
            ->where(function ($q) use ($player) {
                $q->where('home_team_id', $player->team_id)
                    ->orWhere('away_team_id', $player->team_id);
            })
            ->when($targetMatch, fn ($q) => $q->where('id', '!=', $targetMatch->id)->where('match_number', '<', $targetMatch->match_number))
            ->orderByDesc('match_number')
            ->first();

        if ($lastTeamMatch) {
            $gotRedInLastMatch = $events
                ->where('match_id', $lastTeamMatch->id)
                ->whereIn('event_type', ['second_yellow_card', 'red_card'])
                ->isNotEmpty();

            if ($gotRedInLastMatch) {
                return [
                    'is_suspended' => true,
                    'reason' => "Larangan bermain otomatis: Mendapat Kartu Merah pada Pertandingan #{$lastTeamMatch->match_number}.",
                    'yellow_count' => $yellowCount,
                    'red_count' => $redCount,
                ];
            }
        }

        // Akumulasi 2 kartu kuning menghasilkan larangan bermain 1 pertandingan berikutnya
        if ($yellowCount > 0 && $yellowCount % 2 === 0 && $lastTeamMatch) {
            $gotYellowInLastMatch = $events
                ->where('match_id', $lastTeamMatch->id)
                ->where('event_type', 'yellow_card')
                ->isNotEmpty();

            if ($gotYellowInLastMatch) {
                return [
                    'is_suspended' => true,
                    'reason' => "Larangan bermain otomatis: Akumulasi {$yellowCount} Kartu Kuning.",
                    'yellow_count' => $yellowCount,
                    'red_count' => $redCount,
                ];
            }
        }

        return [
            'is_suspended' => false,
            'reason' => null,
            'yellow_count' => $yellowCount,
            'red_count' => $redCount,
        ];
    }

    /**
     * Mengambil daftar Top Scorer dan statistik kartu disiplin per kompetisi.
     */
    public function getEventLeaderboard(Event $event): array
    {
        $completedMatchIds = $event->matches()
            ->where('status', 'completed')
            ->pluck('id');

        if ($completedMatchIds->isEmpty()) {
            $fairPlay = EventGroupTeam::query()
                ->whereHas('group', fn ($q) => $q->where('event_id', $event->id))
                ->with('team:id,name,slug')
                ->get()
                ->map(fn (EventGroupTeam $gt) => [
                    'team_id' => $gt->team_id,
                    'team_name' => $gt->team?->name ?? 'Tim Terhapus',
                    'yellow_cards' => 0,
                    'red_cards' => 0,
                    'fair_play_score' => 0,
                ])
                ->values()
                ->all();

            return [
                'top_scorers' => [],
                'disciplinary' => [],
                'fair_play' => $fairPlay,
            ];
        }

        $events = MatchEvent::query()
            ->with(['player:id,full_name,jersey_number,position', 'team:id,name,slug'])
            ->whereIn('match_id', $completedMatchIds)
            ->whereNotNull('player_id')
            ->get();

        $topScorers = $events
            ->whereIn('event_type', ['goal', 'penalty_goal'])
            ->groupBy('player_id')
            ->map(function (Collection $items) {
                /** @var MatchEvent $first */
                $first = $items->first();

                return [
                    'player_id' => $first->player_id,
                    'player_name' => $first->player?->full_name ?? 'Pemain',
                    'jersey_number' => $first->player?->jersey_number,
                    'position' => $first->player?->position,
                    'team_name' => $first->team?->name ?? '-',
                    'goals' => $items->count(),
                    'total_goals' => $items->count(),
                    'penalty_goals' => $items->where('event_type', 'penalty_goal')->count(),
                ];
            })
            ->sortByDesc('goals')
            ->values()
            ->take(15)
            ->all();

        $disciplinary = $events
            ->whereIn('event_type', ['yellow_card', 'second_yellow_card', 'red_card'])
            ->groupBy('player_id')
            ->map(function (Collection $items) {
                /** @var MatchEvent $first */
                $first = $items->first();
                $yellows = $items->where('event_type', 'yellow_card')->count();
                $reds = $items->whereIn('event_type', ['second_yellow_card', 'red_card'])->count();

                return [
                    'player_id' => $first->player_id,
                    'player_name' => $first->player?->full_name ?? 'Pemain',
                    'jersey_number' => $first->player?->jersey_number,
                    'team_name' => $first->team?->name ?? '-',
                    'yellow_cards' => $yellows,
                    'red_cards' => $reds,
                ];
            })
            ->sortByDesc(fn ($row) => ($row['red_cards'] * 10) + $row['yellow_cards'])
            ->values()
            ->take(15)
            ->all();

        $fairPlay = EventGroupTeam::query()
            ->whereHas('group', fn ($q) => $q->where('event_id', $event->id))
            ->with('team:id,name,slug')
            ->get()
            ->map(fn (EventGroupTeam $gt) => [
                'team_id' => $gt->team_id,
                'team_name' => $gt->team?->name ?? 'Tim Terhapus',
                'yellow_cards' => $gt->yellow_cards,
                'red_cards' => $gt->red_cards,
                'fair_play_score' => ($gt->red_cards * 3) + $gt->yellow_cards,
            ])
            ->sortBy('fair_play_score')
            ->values()
            ->all();

        return [
            'top_scorers' => $topScorers,
            'disciplinary' => $disciplinary,
            'fair_play' => $fairPlay,
        ];
    }
}

