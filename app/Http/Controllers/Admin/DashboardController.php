<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailNotificationLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $events = Event::query()
            ->withOccupiedSlotsCount()
            ->withSum(['payments as revenue_paid' => function ($q) {
                $q->where('status', 'paid');
            }], 'total_amount')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Event $event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'format_label' => Event::FORMATS[$event->format] ?? $event->format,
                    'age_category' => $event->age_category,
                    'status' => $event->status,
                    'max_teams' => $event->max_teams,
                    'occupied_slots' => $event->occupiedSlotsCount(),
                    'active_teams' => (int) ($event->active_teams_count ?? 0),
                    'registration_fee' => (float) $event->registration_fee,
                    'revenue_paid' => (float) ($event->revenue_paid ?? 0),
                ];
            });

        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'total_events' => Event::count(),
                'occupied_slots_total' => Team::query()->occupyingQuotaAt(now())->count(),
                'active_teams_total' => Team::query()->whereIn('status', ['active', 'locked_roster'])->count(),
                'waiting_verification_count' => Payment::query()->where('status', 'waiting_verification')->count(),
                'refund_required_count' => Payment::query()->where('status', 'refund_required')->count(),
                'total_paid_revenue' => (float) Payment::query()->where('status', 'paid')->sum('total_amount'),
                'players_submitted_count' => Player::query()->where('screening_status', 'submitted')->count(),
                'ready_to_lock_teams_count' => Team::query()->where('status', 'active')->where('screening_status', 'all_passed')->count(),
                'locked_teams_count' => Team::query()->where('status', 'locked_roster')->count(),
                'failed_emails_count' => EmailNotificationLog::query()->where('status', 'failed')->count(),
            ],
            'eventsOverview' => $events,
        ]);
    }
}

