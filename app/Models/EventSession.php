<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'session_name',
        'session_date',
        'start_time',
        'end_time',
        'quota',
        'occupied_slots',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'quota' => 'integer',
            'occupied_slots' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AuditionParticipant::class);
    }

    public function isFull(): bool
    {
        return $this->quota !== null && $this->occupied_slots >= $this->quota;
    }
}

