<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pengembalian Dana Selesai</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; margin: 0; padding: 24px; color: #0F172A;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <tr>
            <td style="background: linear-gradient(135deg, #EA580C, #DC2626); padding: 24px 32px; color: #FFFFFF;">
                <div style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">YTEvent Football</div>
                <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">Status Transaksi: PENGEMBALIAN DANA (REFUNDED)</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px;">
                <h1 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0;">Halo, {{ $team->manager_name }}!</h1>
                <p style="font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 20px 0;">
                    Pengembalian dana (refund) atas tagihan pendaftaran tim <strong>{{ $team->name }}</strong> pada kompetisi <strong>{{ $team->event?->name }}</strong> sebesar <strong>Rp {{ number_format($payment->total_amount, 0, ',', '.') }}</strong> telah selesai diproses oleh tim Finance.
                </p>

                <div style="background-color: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #334155; margin-bottom: 6px;">Rincian Penyelesaian Refund</div>
                    <div style="font-size: 14px; color: #0F172A; line-height: 1.6;">
                        No. Invoice: <strong style="font-family: monospace;">{{ $payment->invoice_number }}</strong><br>
                        Nominal Dikembalikan: <strong>Rp {{ number_format($payment->total_amount, 0, ',', '.') }}</strong><br>
                        Catatan / Referensi Transfer: <strong>{{ $refundNotes }}</strong>
                    </div>
                </div>

                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center">
                            <a href="{{ route('manager.payments') }}" style="display: inline-block; background: linear-gradient(135deg, #EA580C, #DC2626); color: #FFFFFF; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 28px; border-radius: 10px;">
                                Lihat Status Tagihan di Portal Manajer
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background-color: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 16px 32px; font-size: 11px; color: #94A3B8; text-align: center;">
                YTEvent Multi-Event Football Tournament System &bull; Konfirmasi resmi penyelesaian pengembalian dana.
            </td>
        </tr>
    </table>
</body>
</html>
