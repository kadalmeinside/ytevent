<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('teams.view_all');
    }

    public function view(User $user, Team $team): bool
    {
        if ($user->can('teams.view_all')) {
            return true;
        }

        return $user->can('manager.portal.access') && (int) $team->user_id === (int) $user->id;
    }

    public function update(User $user, Team $team): bool
    {
        if ($user->can('teams.manage_status')) {
            return true;
        }

        return $user->can('manager.team.update_own')
            && (int) $team->user_id === (int) $user->id
            && ! in_array($team->status, ['disqualified', 'locked_roster'], true);
    }

    public function manageRoster(User $user, Team $team): bool
    {
        return $user->can('manager.players.manage_own')
            && (int) $team->user_id === (int) $user->id
            && $team->canManageRoster();
    }
}

