<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchEvent extends Model
{
    use HasFactory;

    public const TYPES = [
        'goal' => 'Gol Permainan Terbuka',
        'penalty_goal' => 'Gol Penalti (P)',
        'own_goal' => 'Gol Bunuh Diri (OG)',
        'yellow_card' => 'Kartu Kuning (KK)',
        'second_yellow_card' => 'Kartu Kuning Kedua (KK2/KM)',
        'red_card' => 'Kartu Merah Langsung (KM)',
    ];

    protected $fillable = [
        'match_id',
        'team_id',
        'player_id',
        'event_type',
        'minute',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'minute' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}

