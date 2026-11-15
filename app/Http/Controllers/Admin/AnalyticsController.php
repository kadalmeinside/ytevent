<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditionParticipant;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\ScoutingEvaluation;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $events = Event::query()
            ->withOccupiedSlotsCount()
            ->withCount([
                'teams as total_teams_count',
                'teams as screened_teams_count' => fn ($q) => $q->whereIn('screening_status', ['in_review', 'all_passed', 'partially_rejected', 'locked_roster']),
                'teams as passed_teams_count' => fn ($q) => $q->whereIn('screening_status', ['all_passed', 'locked_roster']),
                'auditionParticipants as total_participants_count',
                'auditionParticipants as screened_participants_count' => fn ($q) => $q->whereIn('screening_status', ['submitted', 'passed', 'rejected']),
                'auditionParticipants as passed_participants_count' => fn ($q) => $q->where('screening_status', 'passed'),
            ])
            ->orderByDesc('match_start_date')
            ->orderByDesc('created_at')
            ->get();

        $requestedEventId = $request->filled('event_id') ? (int) $request->query('event_id') : null;
        $eventId = ($requestedEventId && $events->contains('id', $requestedEventId)) ? $requestedEventId : null;
        $days = in_array((int) $request->query('days', 14), [7, 14, 30], true) ? (int) $request->query('days', 14) : 14;

        // 1. KPI & Keuangan
        $paidPaymentsQuery = Payment::query()
            ->where('status', 'paid')
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId));

        $totalRevenuePaid = (float) (clone $paidPaymentsQuery)->sum('total_amount');
        $paidInvoicesCount = (clone $paidPaymentsQuery)->count();

        $pendingRevenue = (float) Payment::query()
            ->whereIn('status', ['pending', 'waiting_verification'])
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->sum('total_amount');

        // 2. Dual Funnel Metrics
        $teamRegistered = Team::query()->when($eventId, fn ($q) => $q->where('event_id', $eventId))->count();
        $teamPaid = Team::query()->when($eventId, fn ($q) => $q->where('event_id', $eventId))->whereIn('status', ['active', 'locked_roster'])->count();
        $teamScreeningPassed = Team::query()->when($eventId, fn ($q) => $q->where('event_id', $eventId))->whereIn('screening_status', ['all_passed', 'locked_roster'])->count();
        $teamLockedRoster = Team::query()->when($eventId, fn ($q) => $q->where('event_id', $eventId))->where('status', 'locked_roster')->count();

        $indRegistered = AuditionParticipant::query()->when($eventId, fn ($q) => $q->where('event_id', $eventId))->count();
        $indPaid = AuditionParticipant::query()->when($eventId, fn ($q) => $q->where('event_id', $eventId))->whereIn('status', ['confirmed', 'active', 'passed'])->count();
        $indScreeningPassed = AuditionParticipant::query()->when($eventId, fn ($q) => $q->where('event_id', $eventId))->where('screening_status', 'passed')->count();
        $indAttendedGate = AuditionParticipant::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereHas('attendances', fn ($q) => $q->where('status', 'present'))
            ->count();
        $indQualified = AuditionParticipant::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereIn('stage_status', ['day_1_passed', 'day_2_passed', 'final_selected'])
            ->count();

        $totalRegistrants = $teamRegistered + $indRegistered;
        $paidRegistrants = $teamPaid + $indPaid;
        $paidConversionRate = $totalRegistrants > 0 ? round(($paidRegistrants / $totalRegistrants) * 100, 1) : 0.0;

        $screenedTeamsTotal = Team::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereIn('screening_status', ['in_review', 'all_passed', 'partially_rejected', 'locked_roster'])
            ->count();
        $screenedParticipantsTotal = AuditionParticipant::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereIn('screening_status', ['submitted', 'passed', 'rejected'])
            ->count();
        $screenedTotal = $screenedTeamsTotal + $screenedParticipantsTotal;
        $screeningPassedTotal = $teamScreeningPassed + $indScreeningPassed;
        $screeningPassRate = $screenedTotal > 0 ? round(($screeningPassedTotal / $screenedTotal) * 100, 1) : 0.0;

        $totalPlayers = Player::query()->when($eventId, fn ($q) => $q->where('event_id', $eventId))->count();
        $totalAthletes = $totalPlayers + $indRegistered;

        // 3. Daily Revenue & Registration Trends
        $startDate = now()->subDays($days - 1)->startOfDay();
        $trendMap = [];
        for ($i = 0; $i < $days; $i++) {
            $cursor = (clone $startDate)->addDays($i);
            $key = $cursor->toDateString();
            $trendMap[$key] = [
                'date' => $key,
                'label' => $cursor->format('d M'),
                'revenue_paid' => 0.0,
                'team_registrations' => 0,
                'individual_registrations' => 0,
                'total_registrations' => 0,
            ];
        }

        $recentPayments = Payment::query()
            ->where('status', 'paid')
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->where(function ($q) use ($startDate) {
                $q->where('paid_at', '>=', $startDate)
                    ->orWhere(function ($sub) use ($startDate) {
                        $sub->whereNull('paid_at')->where('updated_at', '>=', $startDate);
                    });
            })
            ->get(['total_amount', 'paid_at', 'updated_at']);

        foreach ($recentPayments as $payment) {
            $dateKey = ($payment->paid_at ?? $payment->updated_at)?->toDateString();
            if ($dateKey && isset($trendMap[$dateKey])) {
                $trendMap[$dateKey]['revenue_paid'] += (float) $payment->total_amount;
            }
        }

        $recentTeams = Team::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->where('created_at', '>=', $startDate)
            ->get(['created_at']);

        foreach ($recentTeams as $team) {
            $dateKey = $team->created_at?->toDateString();
            if ($dateKey && isset($trendMap[$dateKey])) {
                $trendMap[$dateKey]['team_registrations']++;
                $trendMap[$dateKey]['total_registrations']++;
            }
        }

        $recentParticipants = AuditionParticipant::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->where('created_at', '>=', $startDate)
            ->get(['created_at']);

        foreach ($recentParticipants as $participant) {
            $dateKey = $participant->created_at?->toDateString();
            if ($dateKey && isset($trendMap[$dateKey])) {
                $trendMap[$dateKey]['individual_registrations']++;
                $trendMap[$dateKey]['total_registrations']++;
            }
        }

        $dailyTrends = array_values($trendMap);

        // 4. Document Rejection Bottlenecks
        $docRejectionCounts = [];

        $playerRejections = PlayerDocument::query()
            ->where('validation_status', 'invalid')
            ->when($eventId, fn ($q) => $q->whereHas('player', fn ($p) => $p->where('event_id', $eventId)))
            ->select('document_type', DB::raw('COUNT(*) as total'))
            ->groupBy('document_type')
            ->get();

        foreach ($playerRejections as $row) {
            $type = (string) $row->document_type;
            $docRejectionCounts[$type] = ($docRejectionCounts[$type] ?? 0) + (int) $row->total;
        }

        $participantDocRows = AuditionParticipant::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereNotNull('document_statuses')
            ->get(['document_statuses']);

        foreach ($participantDocRows as $row) {
            if (! is_array($row->document_statuses)) {
                continue;
            }
            foreach ($row->document_statuses as $docKey => $entry) {
                if (is_array($entry) && ($entry['validation_status'] ?? null) === 'invalid') {
                    $key = (string) $docKey;
                    $docRejectionCounts[$key] = ($docRejectionCounts[$key] ?? 0) + 1;
                }
            }
        }

        $totalRejectionsCount = array_sum($docRejectionCounts);
        arsort($docRejectionCounts);

        $documentRejections = collect($docRejectionCounts)->map(function (int $count, string $type) use ($totalRejectionsCount) {
            $label = $type === 'additional_doc'
                ? 'Dokumen Pendukung Audisi'
                : Event::documentLabel($type);

            return [
                'type' => $type,
                'label' => $label,
                'count' => $count,
                'share_percent' => $totalRejectionsCount > 0 ? round(($count / $totalRejectionsCount) * 100, 1) : 0.0,
            ];
        })->values()->all();

        // 5. Demographics & Scouting Distribution
        $positionCounts = [
            'GK' => 0,
            'DF' => 0,
            'MF' => 0,
            'FW' => 0,
        ];

        $playerPositions = Player::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->select('position', DB::raw('COUNT(*) as total'))
            ->groupBy('position')
            ->get();

        foreach ($playerPositions as $row) {
            $code = strtoupper((string) $row->position);
            if (isset($positionCounts[$code])) {
                $positionCounts[$code] += (int) $row->total;
            }
        }

        $participantPositions = AuditionParticipant::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereNotNull('preferred_position')
            ->get(['preferred_position']);

        foreach ($participantPositions as $row) {
            $posCode = $this->normalizePositionBucket((string) $row->preferred_position);
            if ($posCode && isset($positionCounts[$posCode])) {
                $positionCounts[$posCode]++;
            }
        }

        $positionTotal = array_sum($positionCounts);
        $positionLabels = [
            'GK' => 'Kiper (GK)',
            'DF' => 'Bek (DF)',
            'MF' => 'Gelandang (MF)',
            'FW' => 'Penyerang (FW)',
        ];

        $positions = collect($positionCounts)->map(fn (int $count, string $key) => [
            'code' => $key,
            'label' => $positionLabels[$key],
            'count' => $count,
            'percent' => $positionTotal > 0 ? round(($count / $positionTotal) * 100, 1) : 0.0,
        ])->values()->all();

        $clubCounts = [];
        $teamClubs = Team::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereNotNull('origin_association')
            ->get(['origin_association']);
        foreach ($teamClubs as $t) {
            $name = trim((string) $t->origin_association);
            if ($name !== '' && $name !== '-') {
                $clubCounts[$name] = ($clubCounts[$name] ?? 0) + 1;
            }
        }

        $participantClubs = AuditionParticipant::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereNotNull('current_club_ssb')
            ->get(['current_club_ssb']);
        foreach ($participantClubs as $p) {
            $name = trim((string) $p->current_club_ssb);
            if ($name !== '' && $name !== '-') {
                $clubCounts[$name] = ($clubCounts[$name] ?? 0) + 1;
            }
        }
        arsort($clubCounts);
        $topClubs = collect($clubCounts)->take(8)->map(fn (int $count, string $name) => [
            'name' => $name,
            'count' => $count,
        ])->values()->all();

        $evaluations = ScoutingEvaluation::query()
            ->when($eventId, fn ($q) => $q->whereHas('participant', fn ($p) => $p->where('event_id', $eventId)))
            ->get(['quick_grade', 'rating_stars']);

        $totalEvaluations = $evaluations->count();
        $scoutingSummary = [
            'total_evaluations' => $totalEvaluations,
            'grade_a' => $evaluations->where('quick_grade', 'A')->count(),
            'grade_b' => $evaluations->where('quick_grade', 'B')->count(),
            'grade_c' => $evaluations->where('quick_grade', 'C')->count(),
            'avg_stars' => $totalEvaluations > 0
                ? round((float) ($evaluations->whereNotNull('rating_stars')->avg('rating_stars') ?? 0), 2)
                : 0.0,
        ];

        // 6. Event Comparison Table
        $revenueByEvent = Payment::query()
            ->where('status', 'paid')
            ->whereNotNull('event_id')
            ->select('event_id', DB::raw('SUM(total_amount) as revenue'))
            ->groupBy('event_id')
            ->pluck('revenue', 'event_id');

        $eventComparison = $events->map(function (Event $event) use ($revenueByEvent) {
            $isIndividual = $event->isIndividual();
            $capacity = $event->isUnlimitedQuota()
                ? null
                : ($isIndividual ? (int) ($event->max_participants ?: 100) : (int) ($event->max_teams ?: 32));
            $occupied = $event->occupiedSlotsCount();
            $fillRate = ($capacity && $capacity > 0) ? min(100, round(($occupied / $capacity) * 100, 1)) : null;

            $screenedCount = $isIndividual
                ? (int) ($event->screened_participants_count ?? 0)
                : (int) ($event->screened_teams_count ?? 0);
            $passedCount = $isIndividual
                ? (int) ($event->passed_participants_count ?? 0)
                : (int) ($event->passed_teams_count ?? 0);
            $passRate = $screenedCount > 0 ? round(($passedCount / $screenedCount) * 100, 1) : 0.0;

            return [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'participant_type' => $event->participant_type ?? 'team',
                'event_type_label' => Event::EVENT_TYPES[$event->event_type ?? 'tournament'] ?? 'Turnamen',
                'age_category' => $event->age_category,
                'status' => $event->status,
                'quota_type' => $event->quota_type ?? 'fixed',
                'capacity' => $capacity,
                'occupied' => $occupied,
                'fill_rate' => $fillRate,
                'total_registrants' => $isIndividual
                    ? (int) ($event->total_participants_count ?? 0)
                    : (int) ($event->total_teams_count ?? 0),
                'revenue_paid' => (float) ($revenueByEvent->get($event->id) ?? 0),
                'screening_pass_rate' => $passRate,
            ];
        })->values()->all();

        return Inertia::render('Admin/Analytics/Index', [
            'filters' => [
                'event_id' => $eventId,
                'days' => $days,
            ],
            'events' => $events->map(fn (Event $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'participant_type' => $e->participant_type ?? 'team',
                'age_category' => $e->age_category,
            ])->values()->all(),
            'kpi' => [
                'total_revenue_paid' => $totalRevenuePaid,
                'pending_revenue' => $pendingRevenue,
                'paid_invoices_count' => $paidInvoicesCount,
                'total_registrants' => $totalRegistrants,
                'paid_registrants' => $paidRegistrants,
                'paid_conversion_rate' => $paidConversionRate,
                'screening_pass_rate' => $screeningPassRate,
                'total_athletes' => $totalAthletes,
            ],
            'teamFunnel' => [
                'registered' => $teamRegistered,
                'paid' => $teamPaid,
                'screening_passed' => $teamScreeningPassed,
                'locked_roster' => $teamLockedRoster,
            ],
            'individualFunnel' => [
                'registered' => $indRegistered,
                'paid' => $indPaid,
                'screening_passed' => $indScreeningPassed,
                'attended_gate' => $indAttendedGate,
                'qualified' => $indQualified,
            ],
            'dailyTrends' => $dailyTrends,
            'documentRejections' => $documentRejections,
            'demographics' => [
                'positions' => $positions,
                'top_clubs' => $topClubs,
                'scouting' => $scoutingSummary,
            ],
            'eventComparison' => $eventComparison,
        ]);
    }

    protected function normalizePositionBucket(string $raw): ?string
    {
        $pos = strtoupper(trim($raw));
        if ($pos === '') {
            return null;
        }
        if (str_contains($pos, 'GK') || str_contains($pos, 'KIPER') || str_contains($pos, 'PENJAGA GAWANG')) {
            return 'GK';
        }
        if (str_contains($pos, 'BEK') || str_contains($pos, 'DF') || str_contains($pos, 'CB') || str_contains($pos, 'LB') || str_contains($pos, 'RB') || str_contains($pos, 'BACK')) {
            return 'DF';
        }
        if (str_contains($pos, 'GELANDANG') || str_contains($pos, 'MF') || str_contains($pos, 'CM') || str_contains($pos, 'DM') || str_contains($pos, 'AM')) {
            return 'MF';
        }
        if (str_contains($pos, 'PENYERANG') || str_contains($pos, 'FW') || str_contains($pos, 'STRIKER') || str_contains($pos, 'SAYAP') || str_contains($pos, 'ST') || str_contains($pos, 'RW') || str_contains($pos, 'LW')) {
            return 'FW';
        }

        return null;
    }
}
