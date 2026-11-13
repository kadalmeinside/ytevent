<?php

namespace App\Mail;

use App\Models\AuditionParticipant;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuditionPaymentConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AuditionParticipant $participant,
        public Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[YTEvent] Pembayaran Audisi Diterima! Peserta {$this->participant->full_name} Resmi Aktif",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.audition_payment_confirmed',
        );
    }
}

