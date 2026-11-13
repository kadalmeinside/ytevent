<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditionParticipant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'user_id',
        'event_session_id',
        'registration_number',
        'bib_number',
        'full_name',
        'nickname',
        'nik',
        'birth_date',
        'gender',
        'preferred_position',
        'secondary_position',
        'dominant_foot',
        'height_cm',
        'weight_kg',
        'current_club_ssb',
        'playing_experience',
        'jersey_size',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'medical_notes',
        'custom_answers',
        'liability_accepted_at',
        'photo_path',
        'birth_certificate_path',
        'family_card_path',
        'additional_doc_path',
        'screening_status',
        'screening_notes',
        'screening_verified_by',
        'screening_verified_at',
        'screening_code',
        'stage_status',
        'status',
        'slot_reserved_at',
        'slot_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'bib_number' => 'integer',
            'birth_date' => 'date',
            'height_cm' => 'integer',
            'weight_kg' => 'integer',
            'custom_answers' => 'array',
            'liability_accepted_at' => 'datetime',
            'screening_verified_at' => 'datetime',
            'slot_reserved_at' => 'datetime',
            'slot_expires_at' => 'datetime',
        ];
    }

    public const STAGE_STATUSES = [
        'registered' => 'Terdaftar di Tahap Awal',
        'day_1_passed' => 'Lolos ke Hari 2',
        'day_1_eliminated' => 'Gugur di Hari 1',
        'day_2_passed' => 'Lolos ke Hari 3 (Final Day)',
        'day_2_eliminated' => 'Gugur di Hari 2',
        'final_selected' => 'Terpilih Skuad Inti / Akademi',
    ];

    public const SCREENING_STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Menunggu Verifikasi',
        'passed' => 'Berkas Sah (Lolos)',
        'rejected' => 'Perlu Revisi Berkas',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventSession(): BelongsTo
    {
        return $this->belongsTo(EventSession::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(AuditionAttendance::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ScoutingEvaluation::class);
    }

    public function screeningOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screening_verified_by');
    }

    public function isPaid(): bool
    {
        return $this->latestPayment?->status === 'paid';
    }

    public function isScreeningPassed(): bool
    {
        return $this->screening_status === 'passed';
    }

    public function canDownloadBib(): bool
    {
        return $this->isPaid() && $this->isScreeningPassed() && (! empty($this->bib_number) || $this->event?->event_type !== 'audition');
    }

    public function canDownloadPass(): bool
    {
        return $this->canDownloadBib();
    }

    public function canDownloadCertificate(): bool
    {
        if ($this->event?->participant_type !== 'individual' ||
            ! $this->isPaid() || ! $this->isScreeningPassed() ||
            ! in_array($this->stage_status, ['day_1_passed', 'day_2_passed', 'final_selected'], true) ||
            ! ($this->event?->certificates_released || $this->event?->status === 'completed')) {
            return false;
        }

        $requiredStage = match ($this->stage_status) {
            'day_1_passed' => 1,
            'day_2_passed' => 2,
            'final_selected' => $this->event?->auditionStages()->max('stage_number') ?: 1,
        };

        return $this->attendances()
            ->whereHas('stage', fn ($query) => $query->where('stage_number', $requiredStage))
            ->where('status', 'present')
            ->exists();
    }

    public function stageStatusLabel(): string
    {
        return self::STAGE_STATUSES[$this->stage_status] ?? $this->stage_status;
    }

    public function screeningStatusLabel(): string
    {
        return self::SCREENING_STATUSES[$this->screening_status] ?? $this->screening_status;
    }
}
