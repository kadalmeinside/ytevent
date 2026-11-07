<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentRefundedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Team $team,
        public Payment $payment,
        public string $refundNotes
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[YTEvent] Pengembalian Dana (Refund) Selesai — Invoice {$this->payment->invoice_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment_refunded',
        );
    }
}

