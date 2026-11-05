<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SlotExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Team $team
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[YTEvent] Pemberitahuan: Batas Reservasi Kuota Tim {$this->team->name} Telah Kedaluwarsa",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.slot_expired',
        );
    }
}

