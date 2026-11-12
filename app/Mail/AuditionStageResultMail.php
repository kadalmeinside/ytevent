<?php

namespace App\Mail;

use App\Models\AuditionParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuditionStageResultMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AuditionParticipant $participant,
        public string $resultStatus, // 'passed' or 'eliminated'
        public ?string $customNotes = null,
    ) {
        $this->participant->loadMissing(['event', 'eventSession', 'user']);
    }

    public function envelope(): Envelope
    {
        $subjectPrefix = $this->resultStatus === 'passed'
            ? 'Selamat! Hasil Seleksi Tahap:'
            : 'Apresiasi & Piagam Penghargaan:';

        $bibStr = $this->participant->bib_number ? " (Bib #{$this->participant->bib_number})" : '';
        $subject = "[Persija Talent] {$subjectPrefix} {$this->participant->full_name}{$bibStr}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.audition_stage_result',
            with: [
                'participant' => $this->participant,
                'event' => $this->participant->event,
                'resultStatus' => $this->resultStatus,
                'customNotes' => $this->customNotes,
                'stageLabel' => $this->participant->stageStatusLabel(),
            ],
        );
    }
}
