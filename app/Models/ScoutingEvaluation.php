<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoutingEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'audition_participant_id',
        'audition_stage_id',
        'scout_id',
        'quick_grade',
        'rating_stars',
        'tags',
        'coach_notes',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'rating_stars' => 'integer',
            'tags' => 'array',
            'is_locked' => 'boolean',
        ];
    }

    public const QUICK_GRADES = [
        'A' => 'Lolos (A)',
        'B' => 'Cadangan / Pertimbangkan (B)',
        'C' => 'Gugur (C)',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(AuditionParticipant::class, 'audition_participant_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(AuditionStage::class, 'audition_stage_id');
    }

    public function scout(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scout_id');
    }
}
