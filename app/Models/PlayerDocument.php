<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerDocument extends Model
{
    protected $fillable = [
        'player_id',
        'document_type',
        'document_number',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'validation_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function getLabelAttribute(): string
    {
        return Event::documentLabel($this->document_type);
    }
}

