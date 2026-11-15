<?php

namespace App\Services;

use App\Models\AuditionParticipant;
use App\Models\Event;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\User;

class InvoiceService
{
    public const EVENT_TYPE_CODES = [
        'tournament' => 'TRN',
        'festival' => 'TRN',
        'audition' => 'AUD',
        'coaching_clinic' => 'CLN',
    ];

    public const STATUS_META = [
        'paid' => [
            'document_type' => 'RECEIPT',
            'document_title' => 'KWITANSI PEMBAYARAN LUNAS',
            'status_label' => 'LUNAS',
        ],
        'refunded' => [
            'document_type' => 'CREDIT_NOTE',
            'document_title' => 'NOTA PENGEMBALIAN DANA (REFUND)',
            'status_label' => 'REFUND',
        ],
        'waiting_verification' => [
            'document_type' => 'INVOICE',
            'document_title' => 'INVOICE PEMBAYARAN (MENUNGGU VERIFIKASI)',
            'status_label' => 'MENUNGGU VERIFIKASI',
        ],
        'rejected' => [
            'document_type' => 'INVOICE',
            'document_title' => 'INVOICE TAGIHAN (BUKTI DITOLAK)',
            'status_label' => 'DITOLAK',
        ],
        'expired' => [
            'document_type' => 'INVOICE',
            'document_title' => 'INVOICE TAGIHAN KEDALUWARSA',
            'status_label' => 'KEDALUWARSA',
        ],
        'unpaid' => [
            'document_type' => 'INVOICE',
            'document_title' => 'INVOICE TAGIHAN RESMI',
            'status_label' => 'BELUM BAYAR',
        ],
    ];

    public function eventTypeCode(?Event $event): string
    {
        if (! $event) {
            return 'EVT';
        }

        if ($event->event_type === 'audition') {
            return 'AUD';
        }

        if ($event->event_type === 'coaching_clinic') {
            return 'CLN';
        }

        return $event->isIndividual() ? 'IND' : 'TRN';
    }

    public function eventTypeLabel(?Event $event): string
    {
        if (! $event) {
            return 'Event';
        }

        return Event::EVENT_TYPES[$event->event_type]
            ?? ($event->isIndividual() ? 'Event Perorangan' : 'Turnamen Tim Beregu');
    }

    /**
     * Hasilkan nomor invoice terpadu: INV-{YYYY}-{TRN|AUD|CLN|IND}{EVT_ID}-{SEQ}.
     */
    public function generateNumber(Event $lockedEvent): string
    {
        $year = now()->format('Y');
        $prefix = $this->eventTypeCode($lockedEvent)
            . str_pad((string) $lockedEvent->id, 2, '0', STR_PAD_LEFT);
        $seq = Payment::withTrashed()->where('event_id', $lockedEvent->id)->count() + 1;

        do {
            $candidate = sprintf('INV-%s-%s-%04d', $year, $prefix, $seq);
            $exists = Payment::withTrashed()->where('invoice_number', $candidate)->exists();
            $seq++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Bangun snapshot billing immutable saat invoice diterbitkan.
     *
     * @return array<string, mixed>
     */
    public function buildSnapshot(
        ?Event $event,
        ?Team $team = null,
        ?AuditionParticipant $participant = null,
        ?User $payerUser = null
    ): array {
        $issuer = [
            'name' => (string) SystemSetting::get('app_name', config('app.name', 'Persija Development')),
            'tagline' => (string) SystemSetting::get('app_tagline', 'Portal Resmi Kompetisi & Event Sepak Bola'),
            'whatsapp' => SystemSetting::get('contact_whatsapp'),
            'email' => config('mail.from.address'),
        ];

        $billTo = $this->resolveBillTo($team, $participant, $payerUser);

        $eventMeta = [
            'id' => $event?->id,
            'name' => $event?->name ?? 'Event Kompetisi',
            'event_type' => $event?->event_type ?? 'tournament',
            'event_type_label' => $this->eventTypeLabel($event),
            'participant_type' => $event?->isIndividual() ? 'individual' : ($participant ? 'individual' : 'team'),
            'age_category' => $event?->age_category,
            'venue_name' => $event?->venue_name,
            'city' => $event?->city,
            'match_start_date' => $event?->match_start_date?->toDateString(),
            'session_name' => $participant?->eventSession?->session_name,
        ];

        $unitFee = (float) ($event?->registration_fee ?? 0);
        $descParts = array_filter([
            $eventMeta['name'],
            $eventMeta['age_category'] ? 'Kategori ' . $eventMeta['age_category'] : null,
            $eventMeta['session_name'] ? 'Sesi: ' . $eventMeta['session_name'] : null,
        ]);

        return [
            'schema_version' => 1,
            'issuer' => $issuer,
            'bill_to' => $billTo,
            'event' => $eventMeta,
            'line_items' => [
                [
                    'code' => 'REG_FEE',
                    'title' => ($billTo['subject_type'] === 'individual')
                        ? 'Biaya Pendaftaran Peserta Perorangan'
                        : 'Biaya Pendaftaran Tim',
                    'description' => implode(' • ', $descParts),
                    'quantity' => 1,
                    'unit_price' => $unitFee,
                    'amount' => $unitFee,
                ],
            ],
        ];
    }

    /**
     * Resolusi lengkap data invoice (memakai billing_snapshot jika ada, atau fallback kompatibel data lama).
     *
     * @return array<string, mixed>
     */
    public function resolveInvoiceData(Payment $payment): array
    {
        $payment->loadMissing([
            'team.manager',
            'auditionParticipant.user',
            'auditionParticipant.eventSession',
            'event',
            'verifier',
        ]);

        $snapshot = is_array($payment->billing_snapshot) && ! empty($payment->billing_snapshot)
            ? $payment->billing_snapshot
            : $this->buildSnapshot($payment->event, $payment->team, $payment->auditionParticipant);

        $issuer = $snapshot['issuer'] ?? [
            'name' => (string) SystemSetting::get('app_name', config('app.name', 'Persija Development')),
            'tagline' => (string) SystemSetting::get('app_tagline', 'Portal Resmi Kompetisi & Event Sepak Bola'),
            'whatsapp' => SystemSetting::get('contact_whatsapp'),
            'email' => config('mail.from.address'),
        ];

        $billTo = $snapshot['bill_to'] ?? $this->resolveBillTo($payment->team, $payment->auditionParticipant);
        $eventMeta = $snapshot['event'] ?? [
            'id' => $payment->event_id,
            'name' => $payment->event?->name ?? 'Event Kompetisi',
            'event_type' => $payment->event?->event_type ?? 'tournament',
            'event_type_label' => $this->eventTypeLabel($payment->event),
            'participant_type' => $billTo['subject_type'] ?? 'team',
            'age_category' => $payment->event?->age_category,
            'venue_name' => $payment->event?->venue_name,
            'city' => $payment->event?->city,
            'match_start_date' => $payment->event?->match_start_date?->toDateString(),
            'session_name' => $payment->auditionParticipant?->eventSession?->session_name,
        ];

        $baseAmount = (float) $payment->amount;
        $gatewayFee = (float) ($payment->gateway_fee ?? 0);
        $totalAmount = (float) $payment->total_amount;

        $lineItems = $snapshot['line_items'] ?? [];
        if (empty($lineItems)) {
            $lineItems = [[
                'code' => 'REG_FEE',
                'title' => ($billTo['subject_type'] === 'individual')
                    ? 'Biaya Pendaftaran Peserta Perorangan'
                    : 'Biaya Pendaftaran Tim',
                'description' => $eventMeta['name'] ?? '-',
                'quantity' => 1,
                'unit_price' => $baseAmount,
                'amount' => $baseAmount,
            ]];
        } else {
            // Pastikan nominal line item utama sinkron dengan kolom amount tersimpan
            $lineItems[0]['unit_price'] = $baseAmount;
            $lineItems[0]['amount'] = $baseAmount;
        }

        if ($gatewayFee > 0) {
            $lineItems[] = [
                'code' => 'GATEWAY_FEE',
                'title' => 'Biaya Layanan Payment Gateway',
                'description' => $payment->payment_channel ?: strtoupper((string) ($payment->method ?? 'Online')),
                'quantity' => 1,
                'unit_price' => $gatewayFee,
                'amount' => $gatewayFee,
            ];
        }

        $statusMeta = self::STATUS_META[$payment->status] ?? self::STATUS_META['unpaid'];
        $issuedAt = $payment->issued_at ?? $payment->created_at ?? now();

        $verificationHash = strtoupper(substr(hash_hmac(
            'sha256',
            implode('|', [
                (string) $payment->id,
                (string) $payment->invoice_number,
                number_format($totalAmount, 2, '.', ''),
                (string) $payment->status,
            ]),
            (string) config('app.key')
        ), 0, 16));

        $subtitleParts = array_filter([
            $billTo['registration_number'] ?? null,
            $billTo['origin'] ?? null,
            $billTo['contact_name'] ? 'PJ: ' . $billTo['contact_name'] : null,
        ]);

        return [
            'document_type' => $statusMeta['document_type'],
            'document_title' => $statusMeta['document_title'],
            'status_label' => $statusMeta['status_label'],
            'status' => $payment->status,
            'invoice_number' => $payment->invoice_number,
            'issued_at' => $issuedAt->toIso8601String(),
            'issued_at_formatted' => $issuedAt->timezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d M Y, H:i') . ' WIB',
            'expires_at_formatted' => $payment->expires_at?->timezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d M Y, H:i') . ($payment->expires_at ? ' WIB' : ''),
            'paid_at_formatted' => $payment->paid_at?->timezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d M Y, H:i') . ($payment->paid_at ? ' WIB' : ''),
            'verified_at_formatted' => $payment->verified_at?->timezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d M Y, H:i') . ($payment->verified_at ? ' WIB' : ''),
            'refunded_at_formatted' => $payment->refunded_at?->timezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d M Y, H:i') . ($payment->refunded_at ? ' WIB' : ''),
            'issuer' => $issuer,
            'bill_to' => $billTo,
            'payer' => [
                'subject_type' => $billTo['subject_type'] ?? 'team',
                'name' => $billTo['name'] ?? '-',
                'contact_name' => $billTo['contact_name'] ?? '-',
                'contact_phone' => $billTo['contact_phone'] ?? null,
                'email' => $billTo['email'] ?? null,
                'registration_number' => $billTo['registration_number'] ?? null,
                'subtitle' => implode(' • ', $subtitleParts),
            ],
            'event' => $eventMeta,
            'line_items' => $lineItems,
            'subtotal' => $baseAmount,
            'gateway_fee' => $gatewayFee,
            'total_amount' => $totalAmount,
            'currency' => 'IDR',
            'method' => $payment->method,
            'payment_channel' => $payment->payment_channel,
            'gateway_reference' => $payment->gateway_reference,
            'sender_bank_name' => $payment->sender_bank_name,
            'sender_account_holder' => $payment->sender_account_holder,
            'transfer_date' => $payment->transfer_date?->format('d M Y'),
            'verifier_name' => $payment->verifier?->name,
            'verification_notes' => $payment->verification_notes,
            'rejection_reason' => $payment->rejection_reason,
            'refund_amount' => (float) ($payment->refund_amount ?? 0),
            'refund_reason' => $payment->refund_reason,
            'refund_reference' => $payment->refund_reference,
            'verification_hash' => $verificationHash,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveBillTo(
        ?Team $team = null,
        ?AuditionParticipant $participant = null,
        ?User $payerUser = null
    ): array {
        if ($participant) {
            $participant->loadMissing(['user', 'eventSession']);
            $owner = $payerUser ?? $participant->user;

            return [
                'subject_type' => 'individual',
                'subject_label' => 'Peserta Perorangan',
                'name' => $participant->full_name,
                'registration_number' => $participant->registration_number,
                'origin' => $participant->current_club_ssb,
                'contact_name' => $owner?->name ?: ($participant->emergency_contact_name ?: $participant->full_name),
                'contact_phone' => $participant->emergency_contact_phone ?: $owner?->whatsapp,
                'email' => $owner?->email,
            ];
        }

        if ($team) {
            $team->loadMissing('manager');
            $owner = $payerUser ?? $team->manager;

            return [
                'subject_type' => 'team',
                'subject_label' => 'Tim / Klub',
                'name' => $team->name,
                'registration_number' => null,
                'origin' => $team->origin_association,
                'contact_name' => $team->manager_name ?: ($owner?->name ?? '-'),
                'contact_phone' => $team->manager_whatsapp ?: $owner?->whatsapp,
                'email' => $team->manager_email ?: $owner?->email,
            ];
        }

        return [
            'subject_type' => 'team',
            'subject_label' => 'Peserta',
            'name' => '-',
            'registration_number' => null,
            'origin' => null,
            'contact_name' => $payerUser?->name ?? '-',
            'contact_phone' => $payerUser?->whatsapp,
            'email' => $payerUser?->email,
        ];
    }
}

