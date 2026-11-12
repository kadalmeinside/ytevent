<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditionStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'stage_number',
        'stage_name',
        'stage_date',
        'start_time',
        'end_time',
        'venue_name',
        'target_quota',
        'is_active',
        'is_completed',
        'instructions',
    ];

    protected function casts(): array
    {
        return [
            'stage_number' => 'integer',
            'stage_date' => 'date',
            'target_quota' => 'integer',
            'is_active' => 'boolean',
            'is_completed' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(AuditionAttendance::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ScoutingEvaluation::class);
    }
}

