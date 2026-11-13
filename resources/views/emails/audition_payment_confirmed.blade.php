<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pembayaran Audisi Diterima</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; margin: 0; padding: 24px; color: #0F172A;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden;">
        <tr>
            <td style="background: linear-gradient(135deg, #16A34A, #15803D); padding: 24px 32px; color: #FFFFFF;">
                <div style="font-size: 20px; font-weight: 800;">YTEvent Football</div>
                <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">Status Pembayaran Audisi: LUNAS &amp; AKTIF</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px;">
                <h1 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0;">Halo, {{ $participant->full_name }}!</h1>
                <p style="font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 20px 0;">
                    Pembayaran biaya pendaftaran audisi sebesar <strong>Rp {{ number_format($payment->total_amount, 0, ',', '.') }}</strong> telah <strong>BERHASIL DITERIMA &amp; DIVERIFIKASI</strong>.
                </p>
                <div style="background-color: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                    <div style="font-size: 14px; color: #14532D; line-height: 1.6;">
                        Event: <strong>{{ $participant->event?->name }}</strong><br>
                        No. Registrasi: <strong style="font-family: monospace;">{{ $participant->registration_number }}</strong><br>
                        No. Invoice: <strong style="font-family: monospace;">{{ $payment->invoice_number }}</strong><br>
                        Waktu Lunas: <strong>{{ $payment->paid_at?->format('d M Y, H:i') }} WIB</strong>
                    </div>
                </div>
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center">
                            <a href="{{ route('dashboard') }}" style="display: inline-block; background: linear-gradient(135deg, #EA580C, #DC2626); color: #FFFFFF; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 28px; border-radius: 10px;">
                                Buka Portal Peserta &rarr;
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

