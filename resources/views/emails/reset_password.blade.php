<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Atur Ulang Kata Sandi</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; margin: 0; padding: 24px; color: #0F172A;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <tr>
            <td style="background: linear-gradient(135deg, #EA580C, #DC2626); padding: 24px 32px; color: #FFFFFF;">
                <div style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">YTEvent Football</div>
                <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">Pemulihan Akses &amp; Atur Ulang Kata Sandi</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px;">
                <h1 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0;">Halo, {{ $user->name }}!</h1>
                <p style="font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 20px 0;">
                    Kami menerima permohonan untuk mengatur ulang kata sandi akun Anda (<strong>{{ $user->email }}</strong>). Silakan klik tombol di bawah ini untuk membuat kata sandi baru:
                </p>

                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                    <tr>
                        <td align="center">
                            <a href="{{ $resetUrl }}" style="display: inline-block; background: linear-gradient(135deg, #EA580C, #DC2626); color: #FFFFFF; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 28px; border-radius: 10px;">
                                Atur Ulang Kata Sandi Sekarang
                            </a>
                        </td>
                    </tr>
                </table>

                <div style="background-color: #FFF7ED; border-left: 4px solid #EA580C; padding: 12px 16px; margin-bottom: 16px; font-size: 12px; color: #9A3412;">
                    Tautan pemulihan ini berlaku selama <strong>60 menit</strong>. Jika Anda tidak merasa mengajukan permohonan ini, abaikan email ini dan kata sandi Anda tetap aman.
                </div>
            </td>
        </tr>
        <tr>
            <td style="background-color: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 16px 32px; font-size: 11px; color: #94A3B8; text-align: center;">
                YTEvent Multi-Event Football Tournament System
            </td>
        </tr>
    </table>
</body>
</html>
