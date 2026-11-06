<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RosterLockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Team $team) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[YTEvent] Skuad Final {$this->team->name} Resmi Terkunci - Akreditasi Siap Diunduh",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.roster_locked',
            with: [
                'eventName' => $this->team->event?->name,
                'passedCount' => $this->team->players()->where('screening_status', 'passed')->count(),
                'officialsCount' => $this->team->officials()->count(),
            ],
        );
    }
}

