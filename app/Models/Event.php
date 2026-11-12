<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = [
        'banner_url',
        'regulation_pdf_url',
    ];

    public const FORMATS = [
        'cup' => 'Cup (Sistem Gugur)',
        'league' => 'League (Kompetisi Penuh)',
        'group_knockout' => 'Group + Knockout',
        'festival' => 'Grassroots Festival',
    ];

    public const EVENT_TYPES = [
        'tournament' => 'Turnamen Tim Beregu',
        'audition' => 'Audisi / Seleksi Pemain Individual',
        'coaching_clinic' => 'Coaching Clinic / Workshop',
        'festival' => 'Grassroots Festival / Fun Event',
    ];

    public const PARTICIPANT_TYPES = [
        'team' => 'Beregu / Tim',
        'individual' => 'Individu / Perorangan',
    ];

    public const QUOTA_TYPES = [
        'fixed' => 'Kuota Terbatas (Fixed Quota)',
        'unlimited' => 'Terbuka Hingga Batas Waktu (Unlimited until Deadline)',
    ];

    public const DEFAULT_FORM_PRESETS = [
        'photo' => false,
        'position' => true,
        'foot_dominance' => true,
        'team_origin' => true,
        'body_measurements' => true,
        'experience' => true,
        'jersey_size' => false,
    ];

    public const MATCH_FORMATS = [
        '5_vs_5' => [
            'label' => '5 vs 5 (Futsal / Mini Football / U-8 & U-9)',
            'starting_count' => 5,
        ],
        '7_vs_7' => [
            'label' => '7 vs 7 (Mini Soccer / Grassroots U-10 & U-11)',
            'starting_count' => 7,
        ],
        '8_vs_8' => [
            'label' => '8 vs 8 (U-12 Transisi)',
            'starting_count' => 8,
        ],
        '9_vs_9' => [
            'label' => '9 vs 9 (U-12 & U-13 Transisi Lapangan Besar)',
            'starting_count' => 9,
        ],
        '11_vs_11' => [
            'label' => '11 vs 11 (Konvensional / Sepak Bola Standar)',
            'starting_count' => 11,
        ],
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'open' => 'Pendaftaran Dibuka',
        'quota_full' => 'Kuota Penuh',
        'closed' => 'Pendaftaran Ditutup',
        'ongoing' => 'Sedang Berjalan',
        'completed' => 'Selesai',
    ];

    public const DOCUMENT_TYPES = [
        'birth_certificate' => 'Akta Kelahiran',
        'family_card' => 'Kartu Keluarga (KK)',
        'student_card_kia' => 'Kartu Identitas Anak (KIA) / Kartu Pelajar',
        'report_card_diploma' => 'Rapor Sekolah / Ijazah Terakhir',
        'national_id_ktp' => 'Kartu Tanda Penduduk (KTP)',
        'passport' => 'Paspor',
        'domicile_letter' => 'Surat Keterangan Domisili',
    ];

    protected $fillable = [
        'name',
        'slug',
        'banner_path',
        'banner_optimized_path',
        'short_description',
        'venue_name',
        'venue_maps_url',
        'city',
        'event_type',
        'participant_type',
        'quota_type',
        'max_participants',
        'position_quota_caps',
        'form_presets',
        'custom_fields_schema',
        'format',
        'match_format',
        'starting_players_count',
        'dsp_cutoff_minutes',
        'age_category',
        'min_birth_date',
        'max_birth_date',
        'min_players_per_team',
        'max_players_per_team',
        'min_gk_per_team',
        'max_officials_per_team',
        'show_coach_license',
        'require_coach_license',
        'required_documents',
        'max_teams',
        'registration_fee',
        'prize_info',
        'lock_roster_until_paid',
        'payment_deadline_hours',
        'reupload_grace_hours',
        'registration_open_at',
        'registration_close_at',
        'match_start_date',
        'match_end_date',
        'regulation_pdf_path',
        'contact_person',
        'contact_whatsapp',
        'status',
        'is_group_locked',
        'certificates_released',
    ];

    protected function casts(): array
    {
        return [
            'min_birth_date' => 'date',
            'max_birth_date' => 'date',
            'match_start_date' => 'date',
            'match_end_date' => 'date',
            'registration_open_at' => 'datetime',
            'registration_close_at' => 'datetime',
            'required_documents' => 'array',
            'max_teams' => 'integer',
            'max_participants' => 'integer',
            'position_quota_caps' => 'array',
            'form_presets' => 'array',
            'custom_fields_schema' => 'array',
            'min_players_per_team' => 'integer',
            'max_players_per_team' => 'integer',
            'min_gk_per_team' => 'integer',
            'starting_players_count' => 'integer',
            'dsp_cutoff_minutes' => 'integer',
            'max_officials_per_team' => 'integer',
            'show_coach_license' => 'boolean',
            'require_coach_license' => 'boolean',
            'payment_deadline_hours' => 'integer',
            'reupload_grace_hours' => 'integer',
            'registration_fee' => 'decimal:2',
            'lock_roster_until_paid' => 'boolean',
            'is_group_locked' => 'boolean',
            'certificates_released' => 'boolean',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function eventSessions(): HasMany
    {
        return $this->hasMany(EventSession::class);
    }

    public function auditionStages(): HasMany
    {
        return $this->hasMany(AuditionStage::class);
    }

    public function auditionParticipants(): HasMany
    {
        return $this->hasMany(AuditionParticipant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(EventGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class)->orderBy('match_number')->orderBy('kickoff_at');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(EventAnnouncement::class)->orderByDesc('is_pinned')->latest('id');
    }

    public function scopeWithOccupiedSlotsCount(Builder $query): Builder
    {
        $now = now();

        return $query->withCount([
            'teams as occupied_slots_count' => function (Builder $teamQuery) use ($now) {
                $teamQuery->occupyingQuotaAt($now);
            },
            'teams as active_teams_count' => function (Builder $teamQuery) {
                $teamQuery->whereIn('status', ['active', 'locked_roster']);
            },
        ]);
    }

    public function occupiedSlotsCount(): int
    {
        if ($this->isIndividual()) {
            return $this->auditionParticipants()->whereIn('status', ['registered', 'confirmed', 'active', 'passed'])->count();
        }

        if (array_key_exists('occupied_slots_count', $this->attributes)) {
            return (int) $this->attributes['occupied_slots_count'];
        }

        return $this->teams()->occupyingQuotaAt(now())->count();
    }

    public function isUnlimitedQuota(): bool
    {
        return ($this->quota_type ?? 'fixed') === 'unlimited';
    }

    public function isIndividual(): bool
    {
        return ($this->participant_type ?? 'team') === 'individual';
    }

    public function availableSlotsCount(): ?int
    {
        if ($this->isUnlimitedQuota()) {
            return null;
        }

        $capacity = $this->isIndividual()
            ? ($this->max_participants ?? $this->max_teams ?? 0)
            : ($this->max_teams ?? 0);

        return max(0, $capacity - $this->occupiedSlotsCount());
    }

    public function isWithinRegistrationWindow(): bool
    {
        $now = now();

        if ($this->registration_open_at && $now->lt($this->registration_open_at)) {
            return false;
        }

        if ($this->registration_close_at && $now->gt($this->registration_close_at)) {
            return false;
        }

        return true;
    }

    public function canAcceptRegistration(): bool
    {
        if (! in_array($this->status, ['open', 'quota_full'], true)) {
            return false;
        }

        if (! $this->isWithinRegistrationWindow()) {
            return false;
        }

        if ($this->isUnlimitedQuota()) {
            return $this->status === 'open';
        }

        $available = $this->availableSlotsCount();

        return $available !== null && $available > 0;
    }

    public function isBirthDateEligible(?string $birthDate): ?bool
    {
        if (empty($birthDate)) {
            return null;
        }

        if (! $this->min_birth_date && ! $this->max_birth_date) {
            return true;
        }

        $date = Carbon::parse($birthDate);

        if ($this->min_birth_date && $date->lt($this->min_birth_date)) {
            return false;
        }

        if ($this->max_birth_date && $date->gt($this->max_birth_date)) {
            return false;
        }

        return true;
    }

    /**
     * Sinkronisasi status open <-> quota_full. Wajib dipanggil saat baris event dikunci (lockForUpdate).
     */
    public function syncQuotaStatus(?int $occupiedCount = null): void
    {
        if (! in_array($this->status, ['open', 'quota_full'], true)) {
            return;
        }

        if ($this->isUnlimitedQuota()) {
            if ($this->status === 'quota_full' && $this->isWithinRegistrationWindow()) {
                $this->update(['status' => 'open']);
            }
            return;
        }

        $occupied = $occupiedCount ?? $this->teams()->occupyingQuotaAt(now())->count();
        $capacity = $this->isIndividual() ? ($this->max_participants ?? $this->max_teams ?? 0) : ($this->max_teams ?? 0);

        if ($capacity > 0 && $occupied >= $capacity && $this->status !== 'quota_full') {
            $this->update(['status' => 'quota_full']);
        } elseif ($capacity > 0 && $occupied < $capacity && $this->status === 'quota_full' && $this->isWithinRegistrationWindow()) {
            $this->update(['status' => 'open']);
        }
    }

    public function getBannerUrlAttribute(): ?string
    {
        return $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null;
    }

    public function getRegulationPdfUrlAttribute(): ?string
    {
        return $this->regulation_pdf_path ? Storage::disk('public')->url($this->regulation_pdf_path) : null;
    }

    public static function documentLabel(string $type): string
    {
        return self::DOCUMENT_TYPES[$type] ?? $type;
    }
}
