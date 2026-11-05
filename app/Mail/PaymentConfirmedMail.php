<?php

namespace App\Mail;

use App\Models\Team;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Team $team,
        public Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[YTEvent] Pembayaran Diterima! Status Tim {$this->team->name} Resmi Aktif",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment_confirmed',
        );
    }
}

