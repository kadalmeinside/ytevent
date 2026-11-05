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
            ->with(['event', 'latestPayment'])
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
            'status' => $team->status,
            'screening_status' => $team->screening_status,
            'slot_reserved_at' => $team->slot_reserved_at?->toIso8601String(),
            'slot_expires_at' => $team->slot_expires_at?->toIso8601String(),
            'can_manage_roster' => $team->canManageRoster(),
            'logo_url' => $team->logo_path ? Storage::disk('public')->url($team->logo_path) : null,
            'event' => $event ? [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'age_category' => $event->age_category,
                'format' => $event->format,
                'format_label' => Event::FORMATS[$event->format] ?? $event->format,
                'min_birth_date' => $event->min_birth_date?->toDateString(),
                'max_birth_date' => $event->max_birth_date?->toDateString(),
                'min_players_per_team' => $event->min_players_per_team,
                'max_players_per_team' => $event->max_players_per_team,
                'max_officials_per_team' => $event->max_officials_per_team,
                'lock_roster_until_paid' => $event->lock_roster_until_paid,
                'registration_fee' => (float) $event->registration_fee,
                'required_documents' => collect($event->required_documents ?? [])->map(fn (string $type) => [
                    'type' => $type,
                    'label' => Event::documentLabel($type),
                ])->values(),
            ] : null,
        ];
    }
}

