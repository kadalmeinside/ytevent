<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditionAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'audition_participant_id',
        'audition_stage_id',
        'scanned_by',
        'check_in_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(AuditionParticipant::class, 'audition_participant_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(AuditionStage::class, 'audition_stage_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}

