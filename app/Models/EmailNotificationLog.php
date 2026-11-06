<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailNotificationLog extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const MAILABLE_LABELS = [
        'TeamRegisteredMail' => 'Pendaftaran Tim & Tagihan',
        'PaymentConfirmedMail' => 'Konfirmasi Pembayaran Lunas',
        'PaymentProofRejectedMail' => 'Penolakan Bukti Transfer',
        'SlotExpiredMail' => 'Slot Kedaluwarsa',
        'ScreeningResultMail' => 'Hasil Screening Pemain',
        'RosterLockedMail' => 'Skuad Terkunci & Akreditasi',
    ];

    protected $fillable = [
        'event_id',
        'team_id',
        'mailable_type',
        'recipient_email',
        'subject',
        'payload',
        'status',
        'attempts',
        'last_attempted_at',
        'sent_at',
        'error_message',
    ];

    protected $hidden = [
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'attempts' => 'integer',
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

    public function isRetryable(): bool
    {
        return $this->status === self::STATUS_FAILED && ! empty($this->payload);
    }
}
