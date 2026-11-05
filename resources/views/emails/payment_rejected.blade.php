<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pemberitahuan Revisi Bukti Transfer</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; margin: 0; padding: 24px; color: #0F172A;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <tr>
            <td style="background: linear-gradient(135deg, #E11D48, #BE123C); padding: 24px 32px; color: #FFFFFF;">
                <div style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">YTEvent Football</div>
                <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">Verifikasi Pembayaran Manual</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px;">
                <h1 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0;">Halo, {{ $team->manager_name }}!</h1>
                <p style="font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 20px 0;">
                    Setelah dilakukan pemeriksaan oleh tim Finance, bukti transfer manual untuk tagihan <strong>{{ $payment->invoice_number }}</strong> (Tim <strong>{{ $team->name }}</strong>) belum dapat disetujui.
                </p>

                <div style="background-color: #FFF1F2; border: 1px solid #FECDD3; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #9F1239; margin-bottom: 6px;">Alasan Penolakan dari Finance</div>
                    <div style="font-size: 14px; color: #881337; font-weight: 600; line-height: 1.5;">
                        "{{ $reason }}"
                    </div>
                    <div style="font-size: 12px; color: #9F1239; margin-top: 10px; border-top: 1px dashed #FDA4AF; pt-2;">
                        Masa Tenggang Upload Ulang (Grace Period): <strong>{{ $payment->expires_at?->format('d M Y, H:i') }} WIB</strong>
                    </div>
                </div>

                <p style="font-size: 13px; line-height: 1.5; color: #64748B; margin: 0 0 24px 0;">
                    Slot pendaftaran tim Anda masih tetap kami amankan selama masa tenggang di atas. Silakan unggah bukti transfer yang sah dan jelas melalui tautan berikut:
                </p>

                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center">
                            <a href="{{ route('manager.payments') }}" style="display: inline-block; background: linear-gradient(135deg, #EA580C, #DC2626); color: #FFFFFF; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 28px; border-radius: 10px; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.35);">
                                Unggah Ulang Bukti Pembayaran &rarr;
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background-color: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 16px 32px; font-size: 11px; color: #94A3B8; text-align: center;">
                YTEvent Multi-Event Football Tournament System &bull; Jika ada pertanyaan, hubungi panitia kompetisi.
            </td>
        </tr>
    </table>
</body>
</html>
