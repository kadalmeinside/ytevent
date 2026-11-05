<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pemberitahuan Slot Kuota Kedaluwarsa</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; margin: 0; padding: 24px; color: #0F172A;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <tr>
            <td style="background: linear-gradient(135deg, #475569, #334155); padding: 24px 32px; color: #FFFFFF;">
                <div style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">YTEvent Football</div>
                <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">Pemberitahuan Sistem Kuota</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px;">
                <h1 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0;">Halo, {{ $team->manager_name }}!</h1>
                <p style="font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 20px 0;">
                    Kami menginformasikan bahwa batas waktu reservasi pembayaran untuk pendaftaran tim <strong>{{ $team->name }}</strong> pada kompetisi <strong>{{ $team->event?->name }}</strong> telah melewati batas waktu yang ditentukan.
                </p>

                <div style="background-color: #F1F5F9; border: 1px solid #E2E8F0; border-radius: 12px; padding: 18px; margin-bottom: 24px;">
                    <p style="font-size: 13px; color: #334155; margin: 0; line-height: 1.5;">
                        Sesuai regulasi sistem pendaftaran atomik bebas *race-condition*, slot kuota yang sebelumnya ditahan untuk tim Anda kini telah <strong>dilepaskan kembali ke publik</strong> agar dapat diambil oleh tim lainnya.
                    </p>
                </div>

                <p style="font-size: 13px; line-height: 1.5; color: #64748B; margin: 0 0 24px 0;">
                    Jika kuota kompetisi masih tersedia di masa mendatang, Anda dapat mengajukan ulang reservasi slot melalui Portal Manajer tanpa perlu mengisi ulang profil tim dari awal.
                </p>

                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center">
                            <a href="{{ route('manager.payments') }}" style="display: inline-block; background-color: #0F172A; color: #FFFFFF; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 28px; border-radius: 10px;">
                                Masuk ke Portal Manajer Tim &rarr;
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background-color: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 16px 32px; font-size: 11px; color: #94A3B8; text-align: center;">
                YTEvent Multi-Event Football Tournament System &bull; Email otomatis.
            </td>
        </tr>
    </table>
</body>
</html>

