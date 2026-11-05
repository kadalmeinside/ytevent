<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TeamManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Team::class);

        $query = Team::query()
            ->with(['event', 'latestPayment'])
            ->withCount('players')
            ->orderByDesc('created_at');

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('origin_association', 'like', "%{$search}%")
                    ->orWhere('manager_name', 'like', "%{$search}%");
            });
        }

        $teams = $query->get()->map(fn (Team $team) => [
            'id' => $team->id,
            'name' => $team->name,
            'origin_association' => $team->origin_association,
            'manager_name' => $team->manager_name,
            'manager_whatsapp' => $team->manager_whatsapp,
            'manager_email' => $team->manager_email,
            'head_coach_name' => $team->head_coach_name,
            'status' => $team->status,
            'screening_status' => $team->screening_status,
            'players_count' => (int) $team->players_count,
            'slot_expires_at' => $team->slot_expires_at?->toIso8601String(),
            'created_at' => $team->created_at?->toIso8601String(),
            'logo_url' => $team->logo_path ? Storage::disk('public')->url($team->logo_path) : null,
            'event' => $team->event ? [
                'id' => $team->event->id,
                'name' => $team->event->name,
                'age_category' => $team->event->age_category,
            ] : null,
            'latest_payment' => $team->latestPayment ? [
                'invoice_number' => $team->latestPayment->invoice_number,
                'status' => $team->latestPayment->status,
                'total_amount' => (float) $team->latestPayment->total_amount,
            ] : null,
        ]);

        $eventsFilter = Event::query()
            ->select(['id', 'name', 'age_category'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $teams,
            'eventsFilter' => $eventsFilter,
            'filters' => [
                'event_id' => (string) $request->query('event_id', ''),
                'status' => (string) $request->query('status', ''),
                'search' => (string) $request->query('search', ''),
            ],
        ]);
    }
}

