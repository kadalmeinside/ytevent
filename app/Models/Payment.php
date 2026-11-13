<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'team_id',
        'audition_participant_id',
        'event_id',
        'amount',
        'gateway_fee',
        'total_amount',
        'method',
        'payment_channel',
        'external_reference_id',
        'snap_token',
        'payment_url',
        'expires_at',
        'webhook_payload',
        'sender_bank_name',
        'sender_account_holder',
        'sender_account_number',
        'transfer_date',
        'proof_file_path',
        'status',
        'verified_by',
        'rejection_reason',
        'refund_amount',
        'refund_reference',
        'refund_method',
        'refund_notes',
        'refunded_at',
        'paid_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'transfer_date' => 'date',
            'refunded_at' => 'datetime',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'webhook_payload' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function auditionParticipant(): BelongsTo
    {
        return $this->belongsTo(AuditionParticipant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isPastExpiry(): bool
    {
        return $this->expires_at !== null && now()->greaterThanOrEqualTo($this->expires_at);
    }
}

