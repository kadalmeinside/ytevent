<?php

namespace App\Models;

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
        'short_description',
        'venue_name',
        'venue_maps_url',
        'city',
        'format',
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
            'min_players_per_team' => 'integer',
            'max_players_per_team' => 'integer',
            'min_gk_per_team' => 'integer',
            'max_officials_per_team' => 'integer',
            'show_coach_license' => 'boolean',
            'require_coach_license' => 'boolean',
            'payment_deadline_hours' => 'integer',
            'reupload_grace_hours' => 'integer',
            'registration_fee' => 'decimal:2',
            'lock_roster_until_paid' => 'boolean',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
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
        if (array_key_exists('occupied_slots_count', $this->attributes)) {
            return (int) $this->attributes['occupied_slots_count'];
        }

        return $this->teams()->occupyingQuotaAt(now())->count();
    }

    public function availableSlotsCount(): int
    {
        return max(0, $this->max_teams - $this->occupiedSlotsCount());
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

        return $this->availableSlotsCount() > 0;
    }

    public function isBirthDateEligible(?string $birthDate): ?bool
    {
        if (empty($birthDate)) {
            return null;
        }

        if (!$this->min_birth_date && !$this->max_birth_date) {
            return true;
        }

        $date = \Carbon\Carbon::parse($birthDate);

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

        $occupied = $occupiedCount ?? $this->teams()->occupyingQuotaAt(now())->count();

        if ($occupied >= $this->max_teams && $this->status !== 'quota_full') {
            $this->update(['status' => 'quota_full']);
        } elseif ($occupied < $this->max_teams && $this->status === 'quota_full' && $this->isWithinRegistrationWindow()) {
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

