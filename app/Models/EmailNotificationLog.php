<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailNotificationLog extends Model
{
    protected $fillable = [
        'event_id',
        'team_id',
        'mailable_type',
        'recipient_email',
        'status',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}

