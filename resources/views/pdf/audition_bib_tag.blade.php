<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Digital Pass (E-Tiket) - {{ $participant->registration_number }} - {{ $participant->full_name }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 8mm;
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
        .pass-container {
            width: 100%;
            border: 2px solid #cbd5e1;
            border-radius: 16px;
            background: #ffffff;
            overflow: hidden;
        }

        /* Header Table */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            background: #0f172a;
            color: #ffffff;
            padding: 0;
        }
        .header-cell-left {
            padding: 12px 18px;
            vertical-align: middle;
        }
        .header-cell-right {
            padding: 12px 18px;
            text-align: right;
            vertical-align: middle;
        }
        .brand-badge {
            font-size: 7.5pt;
            font-weight: 800;
            color: #fdba74;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .event-title {
            font-size: 17pt;
            font-weight: 900;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: -0.5px;
            margin: 0;
            line-height: 1.15;
        }
        .event-subtitle {
            font-size: 9pt;
            font-weight: bold;
            color: #94a3b8;
            margin: 3px 0 0 0;
        }
        .pass-type-badge {
            display: inline-block;
            background: #ea580c;
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 9.5pt;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pass-age-badge {
            display: inline-block;
            background: #1e293b;
            color: #e2e8f0;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 9.5pt;
            font-weight: 800;
            margin-right: 4px;
        }

        /* Status Banner */
        .status-table {
            width: 100%;
            border-collapse: collapse;
            background: #ecfdf5;
            border-bottom: 2px solid #a7f3d0;
            color: #065f46;
            font-size: 8.5pt;
            font-weight: bold;
        }
        .status-cell-left {
            padding: 5px 18px;
            text-align: left;
        }
        .status-cell-right {
            padding: 5px 18px;
            text-align: right;
            font-family: monospace;
            font-size: 8pt;
        }

        /* Main Content Table (3 Columns) */
        .content-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .col-profile {
            width: 29%;
            vertical-align: top;
            text-align: center;
            padding: 14px 12px;
            border-right: 2px dashed #e2e8f0;
        }
        .col-access {
            width: 43%;
            vertical-align: top;
            padding: 14px 16px;
            border-right: 2px dashed #e2e8f0;
        }
        .col-gate {
            width: 28%;
            vertical-align: top;
            text-align: center;
            padding: 14px 12px;
            background: #f8fafc;
        }

        /* Profile Column Elements */
        .athlete-photo {
            width: 32mm;
            height: 42mm;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #0f172a;
            margin: 0 auto 6px auto;
            display: block;
        }
        .photo-placeholder {
            width: 32mm;
            height: 42mm;
            line-height: 42mm;
            text-align: center;
            background: #f1f5f9;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            font-size: 8pt;
            color: #94a3b8;
            font-weight: bold;
            margin: 0 auto 6px auto;
        }
        .player-name {
            font-size: 12pt;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            line-height: 1.2;
            margin: 0;
        }
        .player-nickname {
            font-size: 9.5pt;
            font-weight: bold;
            color: #ea580c;
            margin: 2px 0 0 0;
        }
        .meta-nik {
            font-family: monospace;
            font-size: 8pt;
            color: #64748b;
            margin-top: 4px;
        }
        .meta-club {
            font-size: 8.5pt;
            font-weight: bold;
            color: #334155;
            margin-top: 3px;
        }
        .meta-birth {
            font-size: 7.5pt;
            color: #64748b;
            margin-top: 2px;
        }

        /* Access Column Elements */
        .card-audition-bib {
            background: #fff7ed;
            border: 2px solid #ea580c;
            border-radius: 12px;
            padding: 10px;
            text-align: center;
            margin-bottom: 10px;
        }
        .card-clinic-access {
            background: #f0fdf4;
            border: 2px solid #16a34a;
            border-radius: 12px;
            padding: 12px 10px;
            text-align: center;
            margin-bottom: 10px;
        }
        .bib-giant-number {
            font-size: 20pt;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.15;
            font-family: monospace;
            letter-spacing: 0.5px;
            margin: 6px 0 4px 0;
        }
        .optional-bib-pill {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #ffedd5;
            border: 1px solid #fdba74;
            color: #9a3412;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .specs-mini-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            font-weight: bold;
            color: #431407;
            margin-top: 4px;
        }
        .specs-mini-table td {
            text-align: center;
            padding: 2px 4px;
        }
        .rules-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 7.5pt;
            color: #334155;
            line-height: 1.45;
        }
        .rules-title {
            font-size: 8pt;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        /* Gate Column Elements */
        .qr-img {
            width: 44mm;
            height: 44mm;
            margin: 0 auto 6px auto;
            display: block;
        }
        .reg-number {
            font-family: monospace;
            font-size: 11pt;
            font-weight: 900;
            color: #0f172a;
            margin-top: 4px;
        }
        .gate-scan-label {
            font-size: 7.5pt;
            font-weight: bold;
            color: #ea580c;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .sec-hash {
            font-family: monospace;
            font-size: 7pt;
            color: #94a3b8;
            margin-top: 3px;
        }
        .seal-badge {
            display: inline-block;
            background: #ecfdf5;
            border: 1.5px solid #059669;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 7pt;
            font-weight: 900;
            color: #065f46;
            margin-top: 8px;
            text-transform: uppercase;
        }

        /* Footer Table */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            background: #0f172a;
            color: #94a3b8;
            font-size: 7pt;
            padding: 0;
        }
        .footer-left {
            padding: 6px 18px;
            text-align: left;
        }
        .footer-right {
            padding: 6px 18px;
            text-align: right;
            color: #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="pass-container">
        <!-- Header Bar -->
        <table class="header-table">
            <tr>
                <td class="header-cell-left">
                    <div class="brand-badge">Persija Development • Official Digital Pass</div>
                    <h1 class="event-title">{{ $event->name }}</h1>
                    <p class="event-subtitle">
                        {{ $event->venue_name ?: 'Training Ground Persija' }} • {{ $event->city ?: 'DKI Jakarta' }}
                        @if($session)
                            • <strong style="color: #fdba74;">{{ $session->session_name }}</strong>
                        @endif
                    </p>
                </td>
                <td class="header-cell-right">
                    <span class="pass-age-badge">{{ $event->age_category ?: 'TALENT' }}</span>
                    <span class="pass-type-badge">
                        {{ strtoupper($event->event_type === 'clinic' ? 'COACHING CLINIC' : ($event->event_type === 'audition' ? 'AUDISI RESMI' : 'DIGITAL PASS')) }}
                    </span>
                </td>
            </tr>
        </table>

        <!-- Status & Screening Banner -->
        <table class="status-table">
            <tr>
                <td class="status-cell-left">
                    ✓ STATUS: LUNAS &amp; TERVERIFIKASI SCREENING RESMI PANITIA
                </td>
                <td class="status-cell-right">
                    KODE VERIFIKASI: <strong>{{ $participant->screening_code ?: 'SCR-VERIFIED' }}</strong>
                </td>
            </tr>
        </table>

        <!-- Main Content 3-Column Table -->
        <table class="content-table">
            <tr>
                <!-- Column 1: Profil Peserta & Anti-Joki -->
                <td class="col-profile">
                    @if($photo_base64)
                        <img class="athlete-photo" src="{{ $photo_base64 }}" alt="Pas Foto" />
                    @else
                        <div class="photo-placeholder">PAS FOTO RESMI</div>
                    @endif
                    <div style="font-size: 6.5pt; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">
                        Verifikasi Wajah Anti-Joki
                    </div>

                    <h2 class="player-name">{{ $participant->full_name }}</h2>
                    @if($participant->nickname)
                        <div class="player-nickname">"{{ $participant->nickname }}"</div>
                    @endif

                    <div class="meta-nik">
                        NIK: {{ substr($participant->nik, 0, 6) . '******' . substr($participant->nik, -4) }}
                    </div>
                    <div class="meta-club">
                        {{ $participant->current_club_ssb ?: 'Peserta Mandiri / Independen' }}
                    </div>
                    <div class="meta-birth">
                        Lahir: {{ $participant->birth_date?->format('d/m/Y') }} (Usia: {{ $participant->birth_date?->age }} Thn)
                    </div>
                </td>

                <!-- Column 2: Akses Kegiatan & Aturan Gerbang -->
                <td class="col-access">
                    @if($event->event_type === 'clinic' || $event->event_type === 'coaching_clinic')
                        <!-- Coaching Clinic / Workshop Allocation Card -->
                        <div class="card-clinic-access">
                            <div style="font-size: 7.5pt; font-weight: 900; color: #166534; letter-spacing: 0.5px; text-transform: uppercase;">
                                ID Digital Pass • Akses Workshop &amp; Clinic
                            </div>
                            <div class="bib-giant-number" style="color: #15803d;">
                                {{ $participant->registration_number ?: ('PASS-' . $participant->id) }}
                            </div>
                            <div style="font-size: 8.5pt; font-weight: bold; color: #166534; margin-top: 2px;">
                                Ukuran Jersey / Kit: <span style="background: #16a34a; color: #ffffff; padding: 1px 6px; border-radius: 4px;">{{ strtoupper($participant->jersey_size ?: 'ALL SIZE') }}</span>
                            </div>
                            <div style="font-size: 7pt; color: #14532d; margin-top: 4px;">
                                *Tunjukkan ID Digital Pass ini saat registrasi gerbang dan pengambilan kit kegiatan.
                            </div>
                        </div>
                    @else
                        <!-- Official Digital Pass Card -->
                        <div class="card-audition-bib">
                            <div style="font-size: 7.5pt; font-weight: 900; color: #c2410c; letter-spacing: 0.5px; text-transform: uppercase;">
                                ID Digital Pass Resmi (E-Tiket)
                            </div>
                            <div class="bib-giant-number">
                                {{ $participant->registration_number ?: ('PASS-' . $participant->id) }}
                            </div>
                            <table class="specs-mini-table">
                                <tr>
                                    <td>POSISI: {{ $participant->preferred_position ?: '-' }}</td>
                                    <td>KAKI: {{ strtoupper($participant->dominant_foot ?: '-') }}</td>
                                    <td>JERSEY: {{ strtoupper($participant->jersey_size ?: 'M') }}</td>
                                </tr>
                            </table>
                            @if($participant->bib_number)
                                <div class="optional-bib-pill">
                                    No. Bib / Dada (Opsional): #{{ $participant->bib_number }}
                                </div>
                            @endif
                            <div style="font-size: 6.5pt; color: #9a3412; margin-top: 4px;">
                                *Gunakan ID Digital Pass &amp; QR Code di samping sebagai tanda pengenal resmi saat check-in gerbang.
                            </div>
                        </div>
                    @endif

                    <!-- Petunjuk Kehadiran Gerbang SOP -->
                    <div class="rules-box">
                        <div class="rules-title">Petunjuk Check-in Gatekeeper:</div>
                        <div>1. Tunjukkan QR Code tiket ini ke petugas gerbang (Gatekeeper).</div>
                        <div>2. Wajib membawa identitas asli (KIA / Kartu Pelajar / Akta Lahir).</div>
                        <div>3. Tiba di lokasi minimal 30 menit sebelum sesi dimulai.</div>
                        <div>4. Mengenakan perlengkapan olahraga lengkap sesuai regulasi kegiatan.</div>
                    </div>
                </td>

                <!-- Column 3: Gatekeeper QR & Validasi -->
                <td class="col-gate">
                    <img class="qr-img" src="{{ $qr_code_svg }}" alt="QR Keabsahan" />
                    <div class="reg-number">{{ $participant->registration_number }}</div>
                    <div class="gate-scan-label">Scan Gatekeeper Check-in</div>
                    <div class="sec-hash">
                        SEC-HASH: {{ substr(md5(($participant->screening_code ?: '') . $participant->id), 0, 16) }}
                    </div>
                    <div class="seal-badge">
                        ✓ Verified Digital Pass
                    </div>
                    <div style="font-size: 6.5pt; color: #64748b; margin-top: 6px;">
                        Tunjukkan QR ini saat memasuki gerbang utama stadion / venue.
                    </div>
                </td>
            </tr>
        </table>

        <!-- Footer Bar -->
        <table class="footer-table">
            <tr>
                <td class="footer-left">
                    Diterbitkan secara resmi oleh Sistem Akreditasi &amp; Tiket Digital Persija Development • {{ $generated_at }}
                </td>
                <td class="footer-right">
                    Penyalahgunaan atau pemalsuan pass digital ini akan ditindak sesuai regulasi yang berlaku.
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
