<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScreeningResultMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Team $team) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[YTEvent] Hasil Screening Pemain Tim {$this->team->name}",
        );
    }

    public function content(): Content
    {
        $players = $this->team->players()
            ->orderBy('jersey_number')
            ->get(['id', 'full_name', 'jersey_number', 'position', 'screening_status', 'rejection_reason']);

        return new Content(
            view: 'emails.screening_result',
            with: [
                'passedPlayers' => $players->where('screening_status', 'passed')->values(),
                'rejectedPlayers' => $players->where('screening_status', 'rejected')->values(),
                'eventName' => $this->team->event?->name,
            ],
        );
    }
}

