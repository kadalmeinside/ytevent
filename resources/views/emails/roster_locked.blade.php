<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Skuad Final Terkunci</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; margin: 0; padding: 24px; color: #0F172A;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden;">
        <tr>
            <td style="background: linear-gradient(135deg, #16A34A, #15803D); padding: 24px 32px; color: #FFFFFF;">
                <div style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">YTEvent Football</div>
                <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">Skuad Final Resmi Terkunci</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px;">
                <h1 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0;">Halo, {{ $team->manager_name }}!</h1>
                <p style="font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 20px 0;">
                    Skuad final tim <strong>{{ $team->name }}</strong> untuk kompetisi <strong>{{ $eventName }}</strong>
                    telah <strong>RESMI DIKUNCI</strong>. Dokumen akreditasi pertandingan kini siap diunduh.
                </p>

                <div style="background-color: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #166534; margin-bottom: 6px;">Ringkasan Skuad</div>
                    <div style="font-size: 14px; color: #14532D; line-height: 1.6;">
                        Pemain Sah: <strong>{{ $passedCount }} pemain</strong><br>
                        Ofisial Terdaftar: <strong>{{ $officialsCount }} orang</strong><br>
                        Waktu Penguncian: <strong>{{ $team->roster_locked_at?->format('d M Y, H:i') }} WIB</strong>
                    </div>
                </div>

                <div style="background-color: #FFF7ED; border-left: 4px solid #EA580C; padding: 12px 16px; margin-bottom: 24px; font-size: 13px; color: #9A3412;">
                    <strong>Dokumen Tersedia:</strong> Album Roster Sheet (untuk pemeriksaan wasit) dan ID Card Akreditasi ber-QR Code untuk seluruh pemain dan ofisial. Cetak dan bawa saat Technical Meeting serta matchday.
                </div>

                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center">
                            <a href="{{ route('manager.accreditation.index') }}" style="display: inline-block; background: linear-gradient(135deg, #EA580C, #DC2626); color: #FFFFFF; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 28px; border-radius: 10px;">
                                Unduh Dokumen Akreditasi &rarr;
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background-color: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 16px 32px; font-size: 11px; color: #94A3B8; text-align: center;">
                YTEvent Multi-Event Football Tournament System &bull; Daftar pemain tidak dapat diubah setelah skuad terkunci.
            </td>
        </tr>
    </table>
</body>
</html>

