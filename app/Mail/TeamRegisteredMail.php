<?php

namespace App\Mail;

use App\Models\Team;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Team $team,
        public Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[YTEvent] Konfirmasi Pendaftaran Tim: {$this->team->name} & Tagihan Reservasi Slot",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team_registered',
        );
    }
}

