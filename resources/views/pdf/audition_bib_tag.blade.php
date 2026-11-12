<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nomor Dada - Bib #{{ $participant->bib_number }} - {{ $participant->full_name }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 10mm;
        }
        * {
            box-sizing: border-box;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        body {
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            color: #0f172a;
        }
        .bib-container {
            width: 100%;
            height: 185mm;
            border: 4px dashed #ea580c;
            border-radius: 20px;
            padding: 12mm 15mm;
            position: relative;
            background: #ffffff;
        }
        .pin-hole {
            position: absolute;
            width: 14mm;
            height: 14mm;
            border: 2px dashed #94a3b8;
            border-radius: 50%;
            text-align: center;
            line-height: 14mm;
            font-size: 8pt;
            color: #94a3b8;
        }
        .pin-tl { top: 6mm; left: 6mm; }
        .pin-tr { top: 6mm; right: 6mm; }
        .pin-bl { bottom: 6mm; left: 6mm; }
        .pin-br { bottom: 6mm; right: 6mm; }

        /* Header Table */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 3px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .event-title {
            font-size: 20pt;
            font-weight: 900;
            color: #ea580c;
            text-transform: uppercase;
            letter-spacing: -0.5px;
            margin: 0;
            line-height: 1.1;
        }
        .event-subtitle {
            font-size: 11pt;
            font-weight: bold;
            color: #475569;
            margin: 3px 0 0 0;
        }
        .event-category-badge {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            padding: 4px 14px;
            border-radius: 8px;
            font-size: 12pt;
            font-weight: 900;
            text-transform: uppercase;
        }

        /* Main Content Table */
        .content-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .left-col {
            width: 25%;
            vertical-align: middle;
            text-align: center;
        }
        .center-col {
            width: 50%;
            vertical-align: middle;
            text-align: center;
        }
        .right-col {
            width: 25%;
            vertical-align: middle;
            text-align: center;
        }

        /* Center Giant Bib Number */
        .bib-number-box {
            background: #ffffff;
            border: 4px solid #ea580c;
            border-radius: 24px;
            padding: 8px 15px;
            display: inline-block;
            min-width: 90%;
            box-shadow: 0 4px 10px rgba(234, 88, 12, 0.15);
        }
        .bib-number {
            font-size: 115pt;
            font-weight: 900;
            color: #ea580c;
            line-height: 0.9;
            letter-spacing: -3px;
            margin: 0;
            font-family: 'Arial Black', Impact, sans-serif;
        }
        .player-name {
            font-size: 22pt;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            margin: 8px 0 0 0;
            letter-spacing: -0.5px;
        }
        .player-nickname {
            font-size: 14pt;
            font-weight: bold;
            color: #ea580c;
            margin: 2px 0 0 0;
        }

        /* Left QR Code Box */
        .qr-box {
            background: #f8fafc;
            border: 2px solid #cbd5e1;
            border-radius: 16px;
            padding: 12px;
            text-align: center;
        }
        .qr-img {
            width: 38mm;
            height: 38mm;
            margin: 0 auto;
        }
        .reg-number {
            font-family: monospace;
            font-size: 9pt;
            font-weight: bold;
            color: #334155;
            margin-top: 6px;
        }
        .anti-joki-label {
            font-size: 7.5pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            margin-top: 4px;
        }

        /* Right Photo & Specs Box */
        .photo-box {
            background: #f8fafc;
            border: 2px solid #cbd5e1;
            border-radius: 16px;
            padding: 10px;
            text-align: center;
        }
        .athlete-photo {
            width: 32mm;
            height: 42mm;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #0f172a;
            margin-bottom: 6px;
        }
        .spec-item {
            font-size: 9pt;
            font-weight: bold;
            color: #1e293b;
            margin: 3px 0;
        }
        .spec-pos {
            background: #ea580c;
            color: #ffffff;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 10pt;
            font-weight: 900;
            display: inline-block;
            margin-bottom: 4px;
        }

        /* Footer Table */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            position: absolute;
            bottom: 8mm;
            left: 15mm;
            right: 15mm;
            width: calc(100% - 30mm);
            border-top: 2px solid #e2e8f0;
            padding-top: 6px;
            font-size: 8pt;
            color: #64748b;
        }
        .footer-auth {
            text-align: right;
            font-weight: bold;
            color: #059669;
        }
    </style>
</head>
<body>
    <div class="bib-container">
        <!-- Pin Holes for Chest Attachment -->
        <div class="pin-hole pin-tl">PENITI</div>
        <div class="pin-hole pin-tr">PENITI</div>
        <div class="pin-hole pin-bl">PENITI</div>
        <div class="pin-hole pin-br">PENITI</div>

        <!-- Header -->
        <table class="header-table">
            <tr>
                <td style="width: 75%;">
                    <h1 class="event-title">{{ $event->name }}</h1>
                    <p class="event-subtitle">
                        {{ $event->venue_name ?: 'Training Ground Persija' }} • {{ $event->city ?: 'DKI Jakarta' }}
                        @if($session)
                            • <strong style="color: #ea580c;">{{ $session->session_name }}</strong>
                        @endif
                    </p>
                </td>
                <td style="width: 25%; text-align: right;">
                    <span class="event-category-badge">{{ $event->age_category ?: 'TALENT' }}</span>
                </td>
            </tr>
        </table>

        <!-- Main Content -->
        <table class="content-table">
            <tr>
                <!-- Left: QR Code & Verification -->
                <td class="left-col">
                    <div class="qr-box">
                        <img class="qr-img" src="{{ $qr_code_svg }}" alt="QR Keabsahan" />
                        <div class="reg-number">{{ $participant->registration_number }}</div>
                        <div class="anti-joki-label">Scan Kehadiran Matchday</div>
                        <div style="font-size: 7.5pt; color: #475569; margin-top: 4px;">
                            NIK: {{ substr($participant->nik, 0, 6) . '******' . substr($participant->nik, -4) }}
                        </div>
                    </div>
                </td>

                <!-- Center: GIANT BIB NUMBER -->
                <td class="center-col">
                    <div class="bib-number-box">
                        <div class="bib-number">{{ $participant->bib_number }}</div>
                    </div>
                    <div class="player-name">{{ $participant->full_name }}</div>
                    @if($participant->nickname)
                        <div class="player-nickname">"{{ $participant->nickname }}"</div>
                    @endif
                </td>

                <!-- Right: Photo & Technical Specs -->
                <td class="right-col">
                    <div class="photo-box">
                        @if($photo_base64)
                            <img class="athlete-photo" src="{{ $photo_base64 }}" alt="Pas Foto" />
                        @else
                            <div class="athlete-photo" style="background: #e2e8f0; line-height: 42mm; color: #94a3b8; font-weight: bold;">FOTO</div>
                        @endif
                        <div>
                            <span class="spec-pos">{{ $participant->preferred_position ?: 'PEMAIN' }}</span>
                        </div>
                        <div class="spec-item">Kaki: {{ strtoupper($participant->dominant_foot ?: '-') }}</div>
                        <div class="spec-item">Tgl Lahir: {{ $participant->birth_date?->format('d/m/Y') }}</div>
                        <div class="spec-item" style="font-size: 8pt; color: #64748b;">
                            {{ $participant->current_club_ssb ?: 'Independen' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Footer -->
        <table class="footer-table">
            <tr>
                <td style="width: 50%;">
                    Kode Keabsahan: <strong style="font-family: monospace;">{{ $participant->screening_code }}</strong> • Diterbitkan: {{ $generated_at }}
                </td>
                <td class="footer-auth" style="width: 50%;">
                    ✓ STATUS SCREENING: SAH &amp; TERVERIFIKASI PANITIA PELAKSANA
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

