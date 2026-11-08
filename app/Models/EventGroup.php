<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function groupTeams(): HasMany
    {
        return $this->hasMany(EventGroupTeam::class)
            ->orderByRaw('CASE WHEN manual_rank_override IS NULL THEN 999 ELSE manual_rank_override END ASC')
            ->orderByDesc('points')
            ->orderByDesc('goal_difference')
            ->orderByDesc('goals_for')
            ->orderBy('red_cards')
            ->orderBy('yellow_cards')
            ->orderBy('id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'event_group_id')
            ->orderBy('match_number')
            ->orderBy('kickoff_at');
    }
}

