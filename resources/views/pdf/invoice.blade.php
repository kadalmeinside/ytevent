<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice['document_title'] }} - {{ $invoice['invoice_number'] }}</title>
    <style>
        @page {
            margin: 14mm 14mm 14mm 14mm;
        }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #0f172a;
            margin: 0;
            padding: 0;
            line-height: 1.45;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .muted {
            color: #64748b;
        }

        .small {
            font-size: 8.5px;
        }

        .mono {
            font-family: DejaVu Sans Mono, Courier, monospace;
        }

        .header-bar {
            border-bottom: 2.5px solid #dc2626;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }

        .issuer-name {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 2px 0;
        }

        .issuer-sub {
            font-size: 9px;
            color: #475569;
        }

        .doc-type {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.6px;
            color: #dc2626;
            text-transform: uppercase;
            margin: 0 0 4px 0;
        }

        .invoice-no {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            font-family: DejaVu Sans Mono, Courier, monospace;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-top: 5px;
        }

        .status-paid {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .status-waiting {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .status-unpaid {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .status-refunded {
            background-color: #e0e7ff;
            color: #3730a3;
            border: 1px solid #a5b4fc;
        }

        .status-muted {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .box-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            vertical-align: top;
        }

        .section-label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .billto-name {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .event-banner {
            background-color: #0f172a;
            color: #ffffff;
            border-radius: 6px;
            padding: 9px 12px;
            margin: 12px 0;
        }

        .event-banner-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
        }

        .event-banner-title {
            font-size: 11px;
            font-weight: bold;
            color: #ffffff;
            margin-top: 1px;
        }

        .items-table {
            margin-top: 8px;
            border: 1px solid #e2e8f0;
        }

        .items-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }

        .items-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .items-table tr:last-child td {
            border-bottom: none;
        }

        .totals-table {
            width: 48%;
            margin-left: auto;
            margin-top: 10px;
        }

        .totals-table td {
            padding: 4px 8px;
            font-size: 9.5px;
        }

        .totals-grand td {
            border-top: 2px solid #0f172a;
            padding-top: 7px;
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }

        .settlement-box {
            margin-top: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            background-color: #ffffff;
        }

        .refund-box {
            margin-top: 10px;
            border: 1px solid #a5b4fc;
            border-radius: 6px;
            padding: 10px 12px;
            background-color: #eef2ff;
        }

        .footer-verify {
            margin-top: 18px;
            padding-top: 12px;
            border-top: 1px dashed #cbd5e1;
        }
    </style>
</head>
<body>
    @php
        $statusClass = match ($invoice['status']) {
            'paid' => 'status-paid',
            'waiting_verification' => 'status-waiting',
            'refunded' => 'status-refunded',
            'expired' => 'status-muted',
            default => 'status-unpaid',
        };
        $billTo = $invoice['bill_to'] ?? [];
        $issuer = $invoice['issuer'] ?? [];
        $event = $invoice['event'] ?? [];
        $formatIdr = fn ($amount) => 'Rp ' . number_format((float) $amount, 0, ',', '.');
    @endphp

    <!-- Header -->
    <div class="header-bar">
        <table>
            <tr>
                <td style="width: 58%; vertical-align: top;">
                    <table>
                        <tr>
                            @if (!empty($logoBase64))
                                <td style="width: 46px; vertical-align: top; padding-right: 10px;">
                                    <img src="{{ $logoBase64 }}" alt="Logo" style="width: 40px; height: 40px; object-fit: contain;">
                                </td>
                            @endif
                            <td style="vertical-align: top;">
                                <div class="issuer-name">{{ $issuer['name'] ?? 'Penyelenggara Event' }}</div>
                                @if (!empty($issuer['tagline']))
                                    <div class="issuer-sub">{{ $issuer['tagline'] }}</div>
                                @endif
                                <div class="issuer-sub" style="margin-top: 3px;">
                                    @if (!empty($issuer['email']))
                                        Email: {{ $issuer['email'] }}
                                    @endif
                                    @if (!empty($issuer['email']) && !empty($issuer['whatsapp']))
                                        &nbsp;|&nbsp;
                                    @endif
                                    @if (!empty($issuer['whatsapp']))
                                        WhatsApp: {{ $issuer['whatsapp'] }}
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 42%; vertical-align: top;" class="text-right">
                    <div class="doc-type">{{ $invoice['document_title'] }}</div>
                    <div class="invoice-no">{{ $invoice['invoice_number'] }}</div>
                    <div>
                        <span class="status-badge {{ $statusClass }}">{{ $invoice['status_label'] }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Bill To & Metadata -->
    <table>
        <tr>
            <td style="width: 54%; padding-right: 8px;" class="box-card">
                <div class="section-label">Ditagihkan Kepada ({{ $billTo['subject_label'] ?? 'Peserta' }})</div>
                <div class="billto-name">{{ $billTo['name'] ?? '-' }}</div>
                @if (!empty($billTo['registration_number']))
                    <div class="small muted">No. Registrasi: <strong class="mono" style="color: #0f172a;">{{ $billTo['registration_number'] }}</strong></div>
                @endif
                @if (!empty($billTo['origin']))
                    <div class="small muted">Asal Klub / SSB / Asosiasi: <strong style="color: #0f172a;">{{ $billTo['origin'] }}</strong></div>
                @endif
                <div class="small muted" style="margin-top: 3px;">
                    Penanggung Jawab / Wali: <strong style="color: #0f172a;">{{ $billTo['contact_name'] ?? '-' }}</strong>
                </div>
                @if (!empty($billTo['contact_phone']) || !empty($billTo['email']))
                    <div class="small muted">
                        @if (!empty($billTo['contact_phone']))
                            Telp/WA: {{ $billTo['contact_phone'] }}
                        @endif
                        @if (!empty($billTo['contact_phone']) && !empty($billTo['email']))
                            &nbsp;•&nbsp;
                        @endif
                        @if (!empty($billTo['email']))
                            {{ $billTo['email'] }}
                        @endif
                    </div>
                @endif
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 42%;" class="box-card">
                <div class="section-label">Informasi Dokumen</div>
                <table>
                    <tr>
                        <td class="small muted" style="padding: 2px 0;">Tanggal Terbit</td>
                        <td class="small text-right" style="padding: 2px 0; font-weight: bold;">{{ $invoice['issued_at_formatted'] }}</td>
                    </tr>
                    @if (!empty($invoice['expires_at_formatted']) && $invoice['status'] !== 'paid')
                        <tr>
                            <td class="small muted" style="padding: 2px 0;">Batas Bayar</td>
                            <td class="small text-right" style="padding: 2px 0; font-weight: bold; color: #b91c1c;">{{ $invoice['expires_at_formatted'] }}</td>
                        </tr>
                    @endif
                    @if (!empty($invoice['paid_at_formatted']))
                        <tr>
                            <td class="small muted" style="padding: 2px 0;">Tanggal Lunas</td>
                            <td class="small text-right" style="padding: 2px 0; font-weight: bold; color: #166534;">{{ $invoice['paid_at_formatted'] }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="small muted" style="padding: 2px 0;">Mata Uang</td>
                        <td class="small text-right" style="padding: 2px 0; font-weight: bold;">IDR (Rupiah)</td>
                    </tr>
                    @if (!empty($invoice['payment_channel']) || !empty($invoice['method']))
                        <tr>
                            <td class="small muted" style="padding: 2px 0;">Kanal Bayar</td>
                            <td class="small text-right" style="padding: 2px 0; font-weight: bold;">
                                {{ $invoice['payment_channel'] ?: strtoupper((string) $invoice['method']) }}
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- Event Highlight Bar -->
    <div class="event-banner">
        <table>
            <tr>
                <td style="width: 68%; vertical-align: middle;">
                    <div class="event-banner-label">{{ $event['event_type_label'] ?? 'Event Kompetisi' }}</div>
                    <div class="event-banner-title">{{ $event['name'] ?? '-' }}</div>
                </td>
                <td style="width: 32%; vertical-align: middle;" class="text-right">
                    @if (!empty($event['age_category']))
                        <div class="event-banner-label">Kategori Usia</div>
                        <div class="event-banner-title">{{ $event['age_category'] }}</div>
                    @endif
                    @if (!empty($event['city']) || !empty($event['venue_name']))
                        <div class="small" style="color: #cbd5e1;">
                            {{ trim(($event['venue_name'] ?? '') . (!empty($event['venue_name']) && !empty($event['city']) ? ', ' : '') . ($event['city'] ?? '')) }}
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Line Items -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th style="width: 52%;">Deskripsi Tagihan</th>
                <th style="width: 10%; text-align: center;">Qty</th>
                <th style="width: 16%; text-align: right;">Harga Satuan</th>
                <th style="width: 16%; text-align: right;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice['line_items'] as $idx => $item)
                <tr>
                    <td class="muted">{{ $idx + 1 }}</td>
                    <td>
                        <div style="font-weight: bold; color: #0f172a;">{{ $item['title'] }}</div>
                        @if (!empty($item['description']))
                            <div class="small muted" style="margin-top: 2px;">{{ $item['description'] }}</div>
                        @endif
                    </td>
                    <td class="text-center">{{ $item['quantity'] ?? 1 }}</td>
                    <td class="text-right">{{ $formatIdr($item['unit_price'] ?? 0) }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ $formatIdr($item['amount'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Summary Totals -->
    <table class="totals-table">
        <tr>
            <td class="muted">Subtotal Tagihan</td>
            <td class="text-right">{{ $formatIdr($invoice['subtotal'] ?? 0) }}</td>
        </tr>
        @if (($invoice['gateway_fee'] ?? 0) > 0)
            <tr>
                <td class="muted">Biaya Layanan Gateway</td>
                <td class="text-right">{{ $formatIdr($invoice['gateway_fee']) }}</td>
            </tr>
        @endif
        <tr class="totals-grand">
            <td>Total Tagihan</td>
            <td class="text-right">{{ $formatIdr($invoice['total_amount'] ?? 0) }}</td>
        </tr>
    </table>

    <!-- Settlement / Payment Audit Details -->
    @if ($invoice['status'] === 'paid' || $invoice['status'] === 'waiting_verification' || !empty($invoice['gateway_reference']) || !empty($invoice['sender_bank_name']))
        <div class="settlement-box">
            <div class="section-label">Rincian Transaksi &amp; Verifikasi Pembayaran</div>
            <table>
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        @if (!empty($invoice['gateway_reference']))
                            <div class="small">Referensi Gateway: <strong class="mono">{{ $invoice['gateway_reference'] }}</strong></div>
                        @endif
                        @if (!empty($invoice['sender_bank_name']))
                            <div class="small">Bank Pengirim: <strong>{{ $invoice['sender_bank_name'] }}</strong></div>
                        @endif
                        @if (!empty($invoice['sender_account_holder']))
                            <div class="small">Atas Nama Pengirim: <strong>{{ $invoice['sender_account_holder'] }}</strong></div>
                        @endif
                        @if (!empty($invoice['transfer_date']))
                            <div class="small">Tanggal Transfer: <strong>{{ $invoice['transfer_date'] }}</strong></div>
                        @endif
                    </td>
                    <td style="width: 50%; vertical-align: top;">
                        @if (!empty($invoice['verified_at_formatted']))
                            <div class="small">Diverifikasi pada: <strong>{{ $invoice['verified_at_formatted'] }}</strong></div>
                        @endif
                        @if (!empty($invoice['verifier_name']))
                            <div class="small">Petugas Verifikator: <strong>{{ $invoice['verifier_name'] }}</strong></div>
                        @endif
                        @if (!empty($invoice['verification_notes']))
                            <div class="small muted" style="margin-top: 2px;">Catatan: {{ $invoice['verification_notes'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <!-- Rejection Alert -->
    @if ($invoice['status'] === 'rejected' && !empty($invoice['rejection_reason']))
        <div class="settlement-box" style="border-color: #fca5a5; background-color: #fef2f2;">
            <div class="section-label" style="color: #991b1b;">Alasan Penolakan Bukti Transfer</div>
            <div class="small" style="color: #7f1d1d;">{{ $invoice['rejection_reason'] }}</div>
        </div>
    @endif

    <!-- Refund Note -->
    @if ($invoice['status'] === 'refunded')
        <div class="refund-box">
            <div class="section-label" style="color: #3730a3;">Informasi Pengembalian Dana (Refund)</div>
            <table>
                <tr>
                    <td class="small" style="width: 50%;">Nominal Refund: <strong>{{ $formatIdr($invoice['refund_amount'] ?: $invoice['total_amount']) }}</strong></td>
                    <td class="small" style="width: 50%;">Tanggal Refund: <strong>{{ $invoice['refunded_at_formatted'] ?: '-' }}</strong></td>
                </tr>
                @if (!empty($invoice['refund_reference']) || !empty($invoice['refund_reason']))
                    <tr>
                        <td class="small">No. Referensi: <strong class="mono">{{ $invoice['refund_reference'] ?: '-' }}</strong></td>
                        <td class="small">Alasan: <strong>{{ $invoice['refund_reason'] ?: '-' }}</strong></td>
                    </tr>
                @endif
            </table>
        </div>
    @endif

    <!-- Footer Verification + QR -->
    <div class="footer-verify">
        <table>
            <tr>
                <td style="width: 78%; vertical-align: middle; padding-right: 12px;">
                    <div class="section-label">Keaslian Dokumen Digital</div>
                    <div class="small muted">
                        Dokumen ini diterbitkan secara elektronik oleh sistem resmi <strong>{{ $issuer['name'] ?? config('app.name') }}</strong> dan sah sebagai bukti tagihan maupun kwitansi pembayaran tanpa memerlukan tanda tangan basah.
                    </div>
                    <div class="small muted" style="margin-top: 4px;">
                        Kode Sidik Digital (HMAC): <strong class="mono" style="color: #0f172a;">{{ $invoice['verification_hash'] }}</strong>
                    </div>
                </td>
                <td style="width: 22%; vertical-align: middle;" class="text-right">
                    <img src="{{ $qrBase64 }}" alt="QR Verification" style="width: 76px; height: 76px; border: 1px solid #e2e8f0; padding: 3px; border-radius: 4px;">
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

