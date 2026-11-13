<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hasil Seleksi Audisi Persija Development</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; margin: 0; padding: 24px; color: #0F172A;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden;">
        <tr>
            <td style="background: linear-gradient(135deg, #EA580C, #DC2626); padding: 24px 32px; color: #FFFFFF;">
                <div style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">PERSIJA DEVELOPMENT</div>
                <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">Pengumuman Resmi Tahap Seleksi Bakat (Talent Scouting)</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px;">
                <h1 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0;">
                    Yth. Orang Tua / Wali dari {{ $participant->full_name }},
                </h1>

                @if($resultStatus === 'passed')
                    <!-- Pesan Lolos -->
                    <div style="background-color: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 12px; padding: 18px; margin-bottom: 20px;">
                        <div style="font-size: 14px; font-weight: 800; text-transform: uppercase; color: #166534; margin-bottom: 4px;">
                            🎉 SELAMAT! RESMI LOLOS KE TAHAP BERIKUTNYA
                        </div>
                        <p style="font-size: 13px; line-height: 1.6; color: #14532D; margin: 0;">
                            Berdasarkan hasil pemantauan teknis dan keputusan dewan pemandu bakat (talent scout) Persija Development pada <strong>{{ $event->name }}</strong>, atlet <strong>{{ $participant->full_name }}</strong> (ID Pass: <strong>{{ $participant->registration_number }}</strong>@if($participant->bib_number) • Rompi: <strong>#{{ $participant->bib_number }}</strong>@endif) dinyatakan <strong>LOLOS</strong> dengan status: <strong>{{ $stageLabel }}</strong>.
                        </p>
                    </div>

                    <div style="background-color: #FFF7ED; border: 1px solid #FFEDD5; border-radius: 12px; padding: 16px; margin-bottom: 24px;">
                        <div style="font-size: 12px; font-weight: 700; color: #C2410C; margin-bottom: 6px; text-transform: uppercase;">
                            Instruksi Kehadiran Tahap Lanjutan:
                        </div>
                        <ul style="font-size: 13px; color: #7C2D12; margin: 0; padding-left: 20px; line-height: 1.6;">
                            <li>Wajib membawa <strong>Digital Pass / E-Tiket</strong> (versi cetak atau layar smartphone).</li>
                            <li>Hadir 30 menit sebelum sesi dimulai untuk registrasi ulang melalui Scanner Gatekeeper.</li>
                            <li>Mengenakan pakaian olahraga, pelindung tulang kering (shin guard), dan sepatu bola standar.</li>
                        </ul>
                    </div>
                @else
                    <!-- Pesan Apresiasi / Eliminasi -->
                    <div style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 18px; margin-bottom: 20px;">
                        <div style="font-size: 14px; font-weight: 800; text-transform: uppercase; color: #334155; margin-bottom: 4px;">
                            APRESIASI &amp; TERIMA KASIH ATAS PERJUANGAN LUAR BIASA
                        </div>
                        <p style="font-size: 13px; line-height: 1.6; color: #475569; margin: 0;">
                            Kami mengucapkan terima kasih yang sebesar-besarnya atas partisipasi, keberanian, dan kerja keras <strong>{{ $participant->full_name }}</strong> dalam audisi <strong>{{ $event->name }}</strong>. Meskipun belum berkesempatan melangkah ke tahap seleksi berikutnya, kami sangat bangga dengan potensi yang ditunjukkan. Perjalanan sepak bola ananda masih sangat panjang!
                        </p>
                    </div>
                @endif

                @if($customNotes)
                    <div style="background-color: #F1F5F9; border-left: 4px solid #EA580C; padding: 12px 16px; border-radius: 4px; margin-bottom: 24px;">
                        <div style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Catatan Tim Pelatih:</div>
                        <p style="font-size: 13px; color: #1E293B; margin: 4px 0 0 0; line-height: 1.5;">{{ $customNotes }}</p>
                    </div>
                @endif

                <!-- Call to Action -->
                <div style="text-align: center; margin: 32px 0 20px 0;">
                    <a href="{{ route('manager.dashboard') }}" style="display: inline-block; background: linear-gradient(135deg, #EA580C, #DC2626); color: #FFFFFF; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 28px; border-radius: 10px; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.25);">
                        Buka Dashboard Peserta
                    </a>
                </div>

                <p style="font-size: 12px; color: #94A3B8; text-align: center; margin: 0;">
                    Anda dapat mengunduh E-Sertifikat resmi dan Digital Pass (E-Tiket) melalui Dashboard akun Anda.
                </p>
            </td>
        </tr>
        <tr>
            <td style="background-color: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 20px 32px; text-align: center; font-size: 11px; color: #64748B;">
                © {{ date('Y') }} Persija Development • Official Talent Scouting System
            </td>
        </tr>
    </table>
</body>
</html>

