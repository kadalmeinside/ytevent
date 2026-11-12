<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Player extends Model
{
    use HasFactory, SoftDeletes;

    public const POSITIONS = [
        'GK' => 'Goalkeeper (GK)',
        'DF' => 'Defender (DF)',
        'MF' => 'Midfielder (MF)',
        'FW' => 'Forward (FW)',
    ];

    protected $fillable = [
        'team_id',
        'event_id',
        'full_name',
        'jersey_name',
        'jersey_number',
        'position',
        'dominant_foot',
        'birth_place',
        'birth_date',
        'nik',
        'nisn',
        'height_cm',
        'weight_kg',
        'photo_path',
        'photo_optimized_path',
        'screening_status',
        'is_locked',
        'rejection_reason',
        'verified_by',
        'verified_at',
        'accreditation_code',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'jersey_number' => 'integer',
            'height_cm' => 'integer',
            'weight_kg' => 'integer',
            'is_locked' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PlayerDocument::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->verifier();
    }

    public function screeningLogs(): HasMany
    {
        return $this->hasMany(ScreeningLog::class);
    }

    public function missingRequiredDocuments(): array
    {
        $rawRequired = $this->event?->required_documents ?? [];
        $requiredTypes = collect($rawRequired)->map(function ($item) {
            return is_array($item) ? ($item['type'] ?? null) : $item;
        })->filter()->values()->all();

        $uploaded = $this->documents->pluck('document_type')->all();

        return array_values(array_diff($requiredTypes, $uploaded));
    }

    public function hasInvalidDocuments(): bool
    {
        if ($this->relationLoaded('documents')) {
            return $this->documents->contains(fn (PlayerDocument $doc) => $doc->validation_status === 'invalid');
        }

        return $this->documents()->where('validation_status', 'invalid')->exists();
    }

    public function isCompleteForSubmission(): bool
    {
        return ! empty($this->photo_path)
            && count($this->missingRequiredDocuments()) === 0
            && ! $this->hasInvalidDocuments();
    }
}
