<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\Team;
use App\Models\User;

class PlayerPolicy
{
    public function view(User $user, Player $player): bool
    {
        if ($user->can('screening.view_queue') || $user->can('teams.view_all')) {
            return true;
        }

        return (int) $player->team?->user_id === (int) $user->id;
    }

    public function create(User $user, Team $team): bool
    {
        return $user->can('manager.players.manage_own')
            && (int) $team->user_id === (int) $user->id
            && $team->canManageRoster();
    }

    public function update(User $user, Player $player): bool
    {
        if ($player->is_locked || $player->screening_status === 'passed') {
            return false;
        }

        return $user->can('manager.players.manage_own')
            && (int) $player->team?->user_id === (int) $user->id
            && $player->team->canManageRoster()
            && in_array($player->screening_status, ['draft', 'rejected'], true);
    }

    public function delete(User $user, Player $player): bool
    {
        if ($player->is_locked || $player->screening_status !== 'draft') {
            return false;
        }

        return $user->can('manager.players.manage_own')
            && (int) $player->team?->user_id === (int) $user->id
            && $player->team->canManageRoster();
    }
}

