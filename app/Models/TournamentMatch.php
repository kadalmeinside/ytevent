<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentMatch extends Model
{
    use HasFactory;

    protected $table = 'tournament_matches';

    public const STAGES = [
        'group' => 'Fase Grup',
        'round_of_16' => 'Babak 16 Besar',
        'quarter_final' => 'Perempat Final (8 Besar)',
        'semi_final' => 'Semi Final',
        'third_place' => 'Perebutan Juara 3',
        'final' => 'Grand Final',
    ];

    public const STATUSES = [
        'scheduled' => 'Terjadwal',
        'ongoing' => 'Sedang Berlangsung',
        'completed' => 'Selesai (FT)',
        'postponed' => 'Ditunda',
    ];

    protected $fillable = [
        'event_id',
        'event_group_id',
        'stage',
        'match_number',
        'home_team_id',
        'away_team_id',
        'kickoff_at',
        'pitch_name',
        'status',
        'home_score',
        'away_score',
        'home_penalty_score',
        'away_penalty_score',
        'referee_name',
        'match_notes',
    ];

    protected function casts(): array
    {
        return [
            'match_number' => 'integer',
            'kickoff_at' => 'datetime',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'home_penalty_score' => 'integer',
            'away_penalty_score' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(EventGroup::class, 'event_group_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function lineups(): HasMany
    {
        return $this->hasMany(MatchLineup::class, 'match_id');
    }

    public function matchEvents(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'match_id')->orderBy('minute')->orderBy('id');
    }

    public static function stageLabel(string $stage): string
    {
        return self::STAGES[$stage] ?? ucfirst(str_replace('_', ' ', $stage));
    }
}

