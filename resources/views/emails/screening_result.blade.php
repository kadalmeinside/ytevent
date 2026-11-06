<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hasil Screening Pemain</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F8FAFC; margin: 0; padding: 24px; color: #0F172A;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden;">
        <tr>
            <td style="background: linear-gradient(135deg, #EA580C, #DC2626); padding: 24px 32px; color: #FFFFFF;">
                <div style="font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">YTEvent Football</div>
                <div style="font-size: 12px; opacity: 0.9; margin-top: 2px;">Rekapitulasi Hasil Screening Berkas Pemain</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px;">
                <h1 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0;">Halo, {{ $team->manager_name }}!</h1>
                <p style="font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 20px 0;">
                    Panitia screening telah menyelesaikan pemeriksaan berkas pemain tim <strong>{{ $team->name }}</strong>
                    pada kompetisi <strong>{{ $eventName }}</strong>. Berikut rekapitulasi hasilnya:
                </p>

                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                    <tr>
                        <td width="50%" style="padding-right: 6px;">
                            <div style="background-color: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 12px; padding: 14px;">
                                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #166534;">Pemain Sah</div>
                                <div style="font-size: 26px; font-weight: 800; color: #14532D;">{{ $passedPlayers->count() }}</div>
                            </div>
                        </td>
                        <td width="50%" style="padding-left: 6px;">
                            <div style="background-color: #FFF1F2; border: 1px solid #FECDD3; border-radius: 12px; padding: 14px;">
                                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #9F1239;">Perlu Revisi</div>
                                <div style="font-size: 26px; font-weight: 800; color: #881337;">{{ $rejectedPlayers->count() }}</div>
                            </div>
                        </td>
                    </tr>
                </table>

                @if($rejectedPlayers->isNotEmpty())
                    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #9F1239; margin-bottom: 8px;">Pemain Yang Perlu Diperbaiki</div>
                    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #FECDD3; border-radius: 10px; margin-bottom: 24px; border-collapse: separate;">
                        @foreach($rejectedPlayers as $player)
                            <tr>
                                <td style="padding: 10px 14px; border-bottom: 1px solid #FFE4E6; font-size: 13px;">
                                    <strong>#{{ $player->jersey_number }} {{ $player->full_name }}</strong>
                                    <span style="color: #64748B;">({{ $player->position }})</span>
                                    <div style="color: #9F1239; font-size: 12px; margin-top: 4px;">Catatan panitia: {{ $player->rejection_reason ?: '-' }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                @if($passedPlayers->isNotEmpty())
                    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #166534; margin-bottom: 8px;">Pemain Dinyatakan Sah</div>
                    <p style="font-size: 13px; line-height: 1.7; color: #334155; margin: 0 0 24px 0;">
                        @foreach($passedPlayers as $player)
                            #{{ $player->jersey_number }} {{ $player->full_name }}@if(! $loop->last), @endif
                        @endforeach
                    </p>
                @endif

                <div style="background-color: #FFF7ED; border-left: 4px solid #EA580C; padding: 12px 16px; margin-bottom: 24px; font-size: 13px; color: #9A3412;">
                    @if($rejectedPlayers->isNotEmpty())
                        <strong>Tindakan Diperlukan:</strong> Perbaiki data dan unggah ulang berkas khusus untuk pemain yang perlu revisi, lalu tekan <strong>Ajukan Ulang Perbaikan</strong>. Pemain yang sudah sah tetap terkunci aman.
                    @else
                        <strong>Tahap Selanjutnya:</strong> Seluruh pemain yang diajukan telah sah. Jika syarat skuad terpenuhi, Anda dapat mengunci skuad final di menu Akreditasi & Skuad.
                    @endif
                </div>

                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center">
                            <a href="{{ $rejectedPlayers->isNotEmpty() ? route('manager.players') : route('manager.accreditation.index') }}" style="display: inline-block; background: linear-gradient(135deg, #EA580C, #DC2626); color: #FFFFFF; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 28px; border-radius: 10px;">
                                {{ $rejectedPlayers->isNotEmpty() ? 'Perbaiki Berkas Pemain' : 'Buka Menu Akreditasi' }} &rarr;
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background-color: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 16px 32px; font-size: 11px; color: #94A3B8; text-align: center;">
                YTEvent Multi-Event Football Tournament System &bull; Email ini dikirim otomatis oleh sistem screening.
            </td>
        </tr>
    </table>
</body>
</html>

