<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat Resmi Audisi - {{ $participant->full_name }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 10mm;
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
            height: 185mm;
            border: 5pt solid #ea580c;
            border-radius: 14pt;
            padding: 5mm;
            background-color: #fffaf5;
            position: relative;
        }
        .cert-inner {
            width: 100%;
            height: 100%;
            border: 2pt solid #fdba74;
            border-radius: 8pt;
            padding: 8mm 12mm;
            background-color: #ffffff;
            text-align: center;
            position: relative;
        }
        .brand-header {
            font-size: 13pt;
            font-weight: 900;
            letter-spacing: 3pt;
            color: #dc2626;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }
        .cert-title {
            font-size: 26pt;
            font-weight: 900;
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
            margin-bottom: 4mm;
        }
        .cert-given {
            font-size: 11pt;
            color: #475569;
            margin-bottom: 2mm;
        }
        .player-name {
            font-size: 24pt;
            font-weight: 900;
            color: #c2410c;
            border-bottom: 2.5pt solid #fed7aa;
            display: inline-block;
            padding: 0 14mm 2mm 14mm;
            margin-bottom: 2mm;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .player-details {
            font-size: 11pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 4mm;
        }
        .predicate-box {
            display: inline-block;
            background: linear-gradient(135deg, #ea580c, #dc2626);
            color: #ffffff;
            font-size: 14pt;
            font-weight: 900;
            letter-spacing: 1.5pt;
            padding: 3.5mm 12mm;
            border-radius: 6pt;
            text-transform: uppercase;
            margin-bottom: 4mm;
        }
        .event-description {
            font-size: 10pt;
            color: #334155;
            line-height: 1.5;
            max-width: 80%;
            margin: 0 auto 5mm auto;
        }
        .signatures-table {
            width: 100%;
            position: absolute;
            bottom: 6mm;
            left: 0;
            right: 0;
            padding: 0 12mm;
        }
        .qr-section {
            width: 25%;
            text-align: left;
            vertical-align: bottom;
        }
        .qr-code-img {
            width: 24mm;
            height: 24mm;
        }
        .sig-col {
            width: 37.5%;
            text-align: center;
            vertical-align: bottom;
        }
        .sig-title {
            font-size: 9pt;
            color: #64748b;
            margin-bottom: 14mm;
        }
        .sig-name {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            border-top: 1pt solid #cbd5e1;
            display: inline-block;
            padding-top: 1.5mm;
            min-width: 48mm;
        }
    </style>
</head>
<body>
    <div class="cert-outer">
        <div class="cert-inner">
            <div class="brand-header">PERSIJA DEVELOPMENT • TALENT SCOUTING</div>
            <h1 class="cert-title">PIAGAM PENGHARGAAN RESMI</h1>
            <div class="cert-number">NO: {{ $certificate_number }}</div>

            <div class="cert-given">Diberikan dengan penuh apresiasi dan kehormatan kepada:</div>
            <div class="player-name">{{ $participant->full_name }}</div>

            <div class="player-details">
                Nomor Dada: <strong>#{{ $participant->bib_number }}</strong> • Posisi: <strong>{{ $participant->preferred_position }}</strong>
                @if($participant->current_club_ssb)
                    • Asal SSB: <strong>{{ $participant->current_club_ssb }}</strong>
                @endif
            </div>

            <div>
                <span class="predicate-box">{{ $predicate_title }}</span>
            </div>

            <p class="event-description">
                Atas dedikasi, sportivitas, disiplin, dan kerja keras yang luar biasa dalam mengikuti seluruh rangkaian uji bakat teknis, fisik, dan taktikal pada <strong>{{ $event->name }}</strong> yang diselenggarakan di {{ $event->city ?: 'DKI Jakarta' }}.
            </p>

            <table class="signatures-table">
                <tr>
                    <td class="qr-section">
                        <img class="qr-code-img" src="{{ $qr_code_svg }}" alt="Verifikasi Keaslian QR" />
                        <div style="font-size: 7pt; color: #94a3b8; font-family: monospace; margin-top: 2px;">
                            Scan Verifikasi Digital
                        </div>
                    </td>
                    <td class="sig-col">
                        <div class="sig-title">Direktur Pengembangan Bakat</div>
                        <div class="sig-name">Ganesha Putera</div>
                    </td>
                    <td class="sig-col">
                        <div class="sig-title">Diterbitkan pada {{ $issued_at }}<br />Ketua Panitia Pelaksana</div>
                        <div class="sig-name">Panitia Persija Talent</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
