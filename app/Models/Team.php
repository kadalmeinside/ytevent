<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'user_id',
        'name',
        'slug',
        'logo_path',
        'origin_association',
        'secretariat_address',
        'manager_name',
        'manager_whatsapp',
        'manager_email',
        'head_coach_name',
        'head_coach_license',
        'terms_accepted_at',
        'status',
        'slot_reserved_at',
        'slot_expires_at',
        'screening_status',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'terms_accepted_at' => 'datetime',
            'slot_reserved_at' => 'datetime',
            'slot_expires_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function officials(): HasMany
    {
        return $this->hasMany(TeamOfficial::class);
    }

    public function scopeOccupyingQuotaAt(Builder $query, CarbonInterface $at): Builder
    {
        return $query->where(function (Builder $q) use ($at) {
            $q->whereIn('status', ['active', 'locked_roster'])
                ->orWhere(function (Builder $pendingQuery) use ($at) {
                    $pendingQuery->where('status', 'pending_payment')
                        ->where(function (Builder $holdQuery) use ($at) {
                            $holdQuery->whereNull('slot_expires_at')
                                ->orWhere('slot_expires_at', '>', $at)
                                ->orWhereHas('payments', function (Builder $paymentQuery) {
                                    $paymentQuery->where('status', 'waiting_verification');
                                });
                        });
                });
        });
    }

    public function canManageRoster(): bool
    {
        if (in_array($this->status, ['disqualified', 'expired', 'locked_roster'], true)) {
            return false;
        }

        if ($this->event && $this->event->lock_roster_until_paid) {
            return $this->status === 'active';
        }

        return in_array($this->status, ['pending_payment', 'active'], true);
    }
}

