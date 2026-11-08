<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat Resmi - {{ $player->full_name }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #0f172a;
            background-color: #ffffff;
        }
        .cert-outer {
            width: 100%;
            height: 182mm;
            border: 4pt solid #ea580c;
            border-radius: 10pt;
            padding: 6mm;
            background-color: #fffaf5;
            position: relative;
        }
        .cert-inner {
            width: 100%;
            height: 100%;
            border: 1.5pt solid #fdba74;
            border-radius: 6pt;
            padding: 8mm 14mm;
            background-color: #ffffff;
            text-align: center;
            position: relative;
        }
        .brand-header {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 3pt;
            color: #dc2626;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }
        .cert-title {
            font-size: 26pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 1.5pt;
            text-transform: uppercase;
            margin: 0;
        }
        .cert-number {
            font-family: 'Courier New', Courier, monospace;
            font-size: 9pt;
            color: #64748b;
            margin-top: 1.5mm;
            margin-bottom: 5mm;
        }
        .cert-given {
            font-size: 11pt;
            color: #475569;
            margin-bottom: 2mm;
        }
        .player-name {
            font-size: 24pt;
            font-weight: bold;
            color: #c2410c;
            border-bottom: 2pt solid #fed7aa;
            display: inline-block;
            padding: 0 12mm 2mm 12mm;
            margin-bottom: 3mm;
        }
        .team-info {
            font-size: 12pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 4mm;
        }
        .predicate-badge {
            display: inline-block;
            background-color: #ea580c;
            color: #ffffff;
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 1pt;
            padding: 2mm 8mm;
            border-radius: 20pt;
            margin-bottom: 4mm;
        }
        .event-desc {
            font-size: 11pt;
            color: #334155;
            line-height: 1.5;
            max-width: 210mm;
            margin: 0 auto 6mm auto;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4mm;
        }
        .sign-line {
            margin-top: 14mm;
            border-bottom: 1pt solid #0f172a;
            width: 55mm;
            display: inline-block;
        }
        .qr-img {
            width: 22mm;
            height: 22mm;
            border: 1pt solid #cbd5e1;
            padding: 1mm;
            border-radius: 4pt;
        }
    </style>
</head>
<body>
    <div class="cert-outer">
        <div class="cert-inner">
            <div class="brand-header">PERSIJA DEVELOPMENT &bull; OFFICIAL TOURNAMENT ACCREDITATION</div>
            <h1 class="cert-title">PIAGAM PENGHARGAAN</h1>
            <div class="cert-number">NOMOR SERTIFIKAT: {{ $certificate_number }} &bull; ID AKREDITASI: {{ $player->accreditation_code }}</div>

            <div class="cert-given">Diberikan secara resmi kepada:</div>
            <div class="player-name">{{ $player->full_name }}</div>
            <div class="team-info">
                #{{ $player->jersey_number }} ({{ $player->position }}) &mdash; {{ $team?->name }} ({{ $team?->origin_association ?: 'SSB / Akademi Peserta' }})
            </div>

            <div>
                <span class="predicate-badge">{{ $predicate }}</span>
            </div>

            <div class="event-desc">
                Atas partisipasi, sportivitas, dan dedikasi dalam kompetisi resmi
                <strong>{{ $event?->name }}</strong> Kategori Usia <strong>{{ $event?->age_category }}</strong>
                yang diselenggarakan di {{ $event?->venue_name ?: 'Stadion Resmi Persija Development' }}{{ $event?->city ? ', ' . $event->city : '' }}.
            </div>

            <table class="footer-table">
                <tr>
                    <td style="width: 33%; text-align: center; vertical-align: bottom; font-size: 9.5pt;">
                        <div>Diterbitkan pada {{ $issued_at }}</div>
                        <div>Direktur Kompetisi,</div>
                        <div class="sign-line"></div>
                        <div style="font-weight: bold; margin-top: 1.5mm;">Komite Pelaksana Turnamen</div>
                        <div style="font-size: 8.5pt; color: #64748b;">Persija Development</div>
                    </td>
                    <td style="width: 34%; text-align: center; vertical-align: bottom;">
                        @if(!empty($qr_code_svg))
                            <img src="{{ $qr_code_svg }}" class="qr-img" alt="QR Verifikasi">
                            <div style="font-size: 7.5pt; color: #64748b; margin-top: 1mm;">PINDAI UNTUK VERIFIKASI KEASLIAN</div>
                        @endif
                    </td>
                    <td style="width: 33%; text-align: center; vertical-align: bottom; font-size: 9.5pt;">
                        <div>Mengetahui,</div>
                        <div>Manajer Tim {{ $team?->name }},</div>
                        <div class="sign-line"></div>
                        <div style="font-weight: bold; margin-top: 1.5mm;">{{ $team?->manager_name ?: 'Manajer Tim' }}</div>
                        <div style="font-size: 8.5pt; color: #64748b;">Ofisial Resmi Tim</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>

