<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Digital Pass (E-Tiket) - {{ $participant->registration_number }} - {{ $participant->full_name }}</title>
    <style>
        @page {
            size: a4 portrait;
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
        .pass-container {
            width: 100%;
            border: 2px solid #cbd5e1;
            border-radius: 16px;
            background: #ffffff;
            overflow: hidden;
        }

        /* Header Overlay Banner */
        .header-wrap {
            position: relative;
            width: 100%;
            background: #0f172a;
            border-bottom: 3px solid #ea580c;
            overflow: hidden;
        }
        .header-banner-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: auto;
            display: block;
            z-index: 1;
        }
        .header-table {
            position: relative;
            z-index: 2;
            width: 100%;
            border-collapse: collapse;
            background: transparent;
            color: #ffffff;
        }
        .header-cell-top {
            padding: 16px 20px 14px 20px;
            vertical-align: middle;
        }
        .header-badges-row {
            margin-bottom: 8px;
        }
        .brand-badge {
            display: inline-block;
            font-size: 7.5pt;
            font-weight: 800;
            color: #fdba74;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .badges-right {
            float: right;
        }
        .pass-age-badge {
            display: inline-block;
            background: #1e293b;
            color: #e2e8f0;
            padding: 3px 9px;
            border-radius: 5px;
            font-size: 8.5pt;
            font-weight: 800;
            margin-right: 4px;
            border: 1px solid #334155;
        }
        .pass-type-badge {
            display: inline-block;
            background: #ea580c;
            color: #ffffff;
            padding: 3px 10px;
            border-radius: 5px;
            font-size: 8.5pt;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .event-title {
            font-size: 17pt;
            font-weight: 900;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: -0.4px;
            margin: 4px 0 0 0;
            line-height: 1.2;
            clear: both;
        }
        .event-subtitle {
            font-size: 8.5pt;
            font-weight: bold;
            color: #cbd5e1;
            margin: 5px 0 0 0;
            line-height: 1.4;
        }

        /* Status & Screening Banner */
        .status-table {
            width: 100%;
            border-collapse: collapse;
            background: #ecfdf5;
            border-bottom: 1.5px solid #a7f3d0;
            color: #065f46;
            font-size: 8pt;
            font-weight: bold;
        }
        .status-cell-left {
            padding: 7px 20px;
            text-align: left;
        }
        .status-cell-right {
            padding: 7px 20px;
            text-align: right;
            font-family: monospace;
            font-size: 7.5pt;
        }

        /* Content Area + Watermark */
        .content-wrap {
            position: relative;
            padding: 16px 20px;
            overflow: hidden;
        }
        .watermark-layer {
            position: absolute;
            top: 8px;
            left: -20px;
            right: -20px;
            z-index: 0;
            color: #f1f5f9;
            font-size: 9pt;
            font-weight: 900;
            letter-spacing: 2px;
            line-height: 3.1;
            text-align: center;
            text-transform: uppercase;
        }
        .content-inner {
            position: relative;
            z-index: 1;
        }

        /* Hero Pass ID Card */
        .card-audition-pass {
            background: #fff7ed;
            border: 2px solid #ea580c;
            border-radius: 12px;
            padding: 12px 16px;
            text-align: center;
            margin-bottom: 14px;
        }
        .card-clinic-pass {
            background: #f0fdf4;
            border: 2px solid #16a34a;
            border-radius: 12px;
            padding: 12px 16px;
            text-align: center;
            margin-bottom: 14px;
        }
        .pass-kicker {
            font-size: 8pt;
            font-weight: 900;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
        .pass-id-number {
            font-size: 24pt;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.15;
            font-family: monospace;
            letter-spacing: 1px;
            margin: 5px 0;
        }
        .specs-mini-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            font-weight: bold;
            color: #431407;
            margin-top: 6px;
            border-top: 1px dashed #fdba74;
        }
        .specs-mini-table td {
            text-align: center;
            padding: 6px 4px 2px 4px;
        }
        .optional-bib-pill {
            display: inline-block;
            margin-top: 6px;
            padding: 2px 10px;
            border-radius: 999px;
            background: #ffedd5;
            border: 1px solid #fdba74;
            color: #9a3412;
            font-size: 7.5pt;
            font-weight: bold;
        }

        /* 2-Column Main Identity & QR Table */
        .body-grid-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
        }
        .col-profile {
            width: 55%;
            vertical-align: top;
            padding: 14px 16px;
            border-right: 2px dashed #cbd5e1;
            background: #ffffff;
        }
        .col-gate {
            width: 45%;
            vertical-align: middle;
            text-align: center;
            padding: 14px 14px;
            background: #f8fafc;
        }

        .profile-inner-table {
            width: 100%;
            border-collapse: collapse;
        }
        .profile-photo-cell {
            width: 34mm;
            vertical-align: top;
            text-align: center;
            padding-right: 12px;
        }
        .profile-detail-cell {
            vertical-align: top;
        }
        .athlete-photo {
            width: 32mm;
            height: 42mm;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #0f172a;
            display: block;
            margin: 0 auto 4px auto;
        }
        .photo-placeholder {
            width: 32mm;
            height: 42mm;
            line-height: 42mm;
            text-align: center;
            background: #f1f5f9;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            font-size: 7.5pt;
            color: #94a3b8;
            font-weight: bold;
            margin: 0 auto 4px auto;
        }
        .section-label {
            font-size: 6.5pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .player-name {
            font-size: 13pt;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            line-height: 1.2;
            margin: 0 0 2px 0;
        }
        .player-nickname {
            font-size: 9pt;
            font-weight: bold;
            color: #ea580c;
            margin: 0 0 6px 0;
        }
        .identity-line {
            font-size: 8pt;
            color: #334155;
            margin-bottom: 4px;
            line-height: 1.35;
        }
        .identity-muted {
            font-family: monospace;
            font-size: 7.5pt;
            color: #64748b;
        }

        /* Gatekeeper QR Elements */
        .qr-img {
            width: 44mm;
            height: 44mm;
            margin: 0 auto 6px auto;
            display: block;
            background: #ffffff;
            padding: 4px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .reg-number {
            font-family: monospace;
            font-size: 10.5pt;
            font-weight: 900;
            color: #0f172a;
            margin-top: 2px;
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
            font-size: 7.5pt;
            color: #475569;
            margin-top: 4px;
        }
        .seal-badge {
            display: inline-block;
            background: #ecfdf5;
            border: 1.5px solid #059669;
            border-radius: 6px;
            padding: 3px 10px;
            font-size: 7pt;
            font-weight: 900;
            color: #065f46;
            margin-top: 6px;
            text-transform: uppercase;
        }

        /* Audit & Validity Box */
        .auth-meta-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 9px 14px;
            margin-bottom: 12px;
            font-size: 7.5pt;
            color: #334155;
        }
        .auth-meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .auth-meta-table td {
            padding: 2px 4px;
            vertical-align: top;
        }
        .stages-box {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 7px 12px;
            margin-bottom: 10px;
            font-size: 7.2pt;
            color: #334155;
        }
        .stages-title {
            font-size: 7.2pt;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .stages-table {
            width: 100%;
            border-collapse: collapse;
        }
        .stages-table td {
            padding: 1.5px 3px;
            vertical-align: top;
        }

        /* Perforation Divider */
        .ticket-divider {
            border-top: 2px dashed #cbd5e1;
            margin: 10px 0 12px 0;
        }

        /* Gatekeeper SOP Box */
        .rules-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 8pt;
            color: #334155;
            line-height: 1.55;
        }
        .rules-title {
            font-size: 8pt;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        /* Footer Table */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            background: #0f172a;
            color: #94a3b8;
            font-size: 7pt;
        }
        .footer-left {
            padding: 8px 20px;
            text-align: left;
        }
        .footer-right {
            padding: 8px 20px;
            text-align: right;
            color: #cbd5e1;
        }
    </style>
</head>
<body>
    @php
        $presets = $form_presets ?? ($event?->normalizedFormPresets() ?? \App\Models\Event::DEFAULT_FORM_PRESETS);
        $customItems = $custom_field_items ?? \App\Services\AuditionBibPdfService::extractCustomFieldItems($event, $participant);
        $docLabels = $required_document_labels ?? \App\Services\AuditionBibPdfService::extractRequiredDocumentLabels($event);
        $isFree = $is_free_event ?? (((float) ($event?->registration_fee ?? 0)) <= 0 && ! $participant->latestPayment);
        $cutoffMins = $checkin_cutoff_minutes ?? max(0, (int) ($event?->dsp_cutoff_minutes ?? 30));

        $stagesList = $stages_summary ?? (($event && $event->relationLoaded('auditionStages'))
            ? $event->auditionStages->sortBy('stage_number')->map(static function ($stage): array {
                $timeParts = array_filter([$stage->start_time, $stage->end_time]);
                return [
                    'stage_number' => $stage->stage_number,
                    'stage_name' => $stage->stage_name,
                    'date_label' => $stage->stage_date?->translatedFormat('d M Y'),
                    'time_label' => count($timeParts) > 0 ? implode(' - ', $timeParts) . ' WIB' : null,
                    'venue_name' => $stage->venue_name,
                ];
            })->values()->all()
            : []);

        $eventTypeKey = $event?->event_type ?? 'audition';
        $isClinic = in_array($eventTypeKey, ['clinic', 'coaching_clinic'], true);
        $isFestival = $eventTypeKey === 'festival';
        $typeBadgeLabel = match ($eventTypeKey) {
            'clinic', 'coaching_clinic' => 'COACHING CLINIC',
            'festival' => 'GRASSROOTS FESTIVAL',
            'audition' => 'AUDISI RESMI',
            'tournament' => 'KOMPETISI RESMI',
            default => 'DIGITAL PASS',
        };
        $accentHex = $isClinic ? '#16a34a' : ($isFestival ? '#0284c7' : '#ea580c');
    @endphp
    <div class="pass-container">
        <!-- Header Overlay Banner -->
        <div class="header-wrap" style="border-bottom-color: {{ $accentHex }};">
            @if(! empty($banner_overlay_base64))
                <img class="header-banner-img" src="{{ $banner_overlay_base64 }}" alt="Banner Event" />
            @endif
            <table class="header-table">
                <tr>
                    <td class="header-cell-top">
                        <div class="header-badges-row">
                            <span class="brand-badge">Persija Development &bull; Official Digital Pass</span>
                            <span class="badges-right">
                                @if(! empty($event?->age_category))
                                    <span class="pass-age-badge">{{ $event->age_category }}</span>
                                @endif
                                <span class="pass-type-badge" style="background: {{ $accentHex }};">
                                    {{ $typeBadgeLabel }}
                                </span>
                            </span>
                        </div>
                        <h1 class="event-title">{{ $event?->name }}</h1>
                        <p class="event-subtitle">
                            {{ $event?->venue_name ?: 'Lokasi Resmi Penyelenggara' }}@if(! empty($event?->city)) &bull; {{ $event->city }}@endif
                            <br />
                            Berlaku: <strong style="color: #ffffff;">{{ $valid_dates_label }}</strong>
                            @if($session)
                                &bull; Sesi: <strong style="color: #fdba74;">{{ $session->session_name }}</strong>
                            @endif
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Status & Screening Banner -->
        <table class="status-table">
            <tr>
                <td class="status-cell-left">
                    <span style="display: inline-block; background: #059669; color: #ffffff; padding: 1px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; margin-right: 5px;">SAH</span>
                    @if($isFree)
                        STATUS: TERDAFTAR &amp; TERVERIFIKASI SCREENING RESMI
                    @else
                        STATUS: LUNAS &amp; TERVERIFIKASI SCREENING RESMI
                    @endif
                </td>
                <td class="status-cell-right">
                    @if($isFree)
                        BEBAS BIAYA &bull; KODE: <strong>{{ $participant->screening_code ?: 'SCR-VERIFIED' }}</strong>
                    @else
                        INV: <strong>{{ $invoice_number }}</strong> &bull; KODE: <strong>{{ $participant->screening_code ?: 'SCR-VERIFIED' }}</strong>
                    @endif
                </td>
            </tr>
        </table>

        <!-- Main Portrait Pass Body with Micro-Watermark -->
        <div class="content-wrap">
            <div class="watermark-layer">
                @for($wm = 0; $wm < 11; $wm++)
                    <div>PERSIJA DEVELOPMENT &bull; {{ $participant->registration_number ?: ('PASS-' . $participant->id) }} &bull; {{ $auth_signature }}</div>
                @endfor
            </div>

            <div class="content-inner">
                @php
                    $showPhotoCell = ! empty($photo_base64) || ! empty($presets['photo']);

                    $specItems = [];
                    if (! empty($presets['position'])) {
                        $posLabel = $participant->preferred_position ?: '-';
                        if ($participant->secondary_position) {
                            $posLabel .= ' / ' . $participant->secondary_position;
                        }
                        $specItems[] = 'POSISI: ' . $posLabel;
                    }
                    if (! empty($presets['foot_dominance'])) {
                        $specItems[] = 'KAKI: ' . strtoupper($participant->dominant_foot ?: '-');
                    }
                    if (! empty($presets['jersey_size'])) {
                        $specItems[] = 'JERSEY: ' . strtoupper($participant->jersey_size ?: '-');
                    }
                @endphp

                <!-- 1. Hero ID Digital Pass Highlight -->
                @if($isClinic)
                    <div class="card-clinic-pass">
                        <div class="pass-kicker" style="color: #166534;">
                            ID Digital Pass Resmi &bull; Akses Workshop &amp; Clinic
                        </div>
                        <div class="pass-id-number" style="color: #15803d;">
                            {{ $participant->registration_number ?: ('PASS-' . $participant->id) }}
                        </div>
                        @if(! empty($presets['jersey_size']))
                            <div style="font-size: 8.5pt; font-weight: bold; color: #166534; margin-top: 2px;">
                                Ukuran Jersey / Kit:
                                <span style="background: #16a34a; color: #ffffff; padding: 1px 7px; border-radius: 4px;">
                                    {{ strtoupper($participant->jersey_size ?: '-') }}
                                </span>
                            </div>
                        @endif
                        @if($participant->bib_number)
                            <div class="optional-bib-pill" style="background: #dcfce7; border-color: #86efac; color: #166534;">
                                No. Bib / Dada (Opsional): #{{ $participant->bib_number }}
                            </div>
                        @endif
                    </div>
                @else
                    <div class="card-audition-pass" @if($isFestival) style="background: #f0f9ff; border-color: #0284c7;" @endif>
                        <div class="pass-kicker" style="color: {{ $isFestival ? '#0369a1' : '#c2410c' }};">
                            ID Digital Pass Resmi ({{ $isFestival ? 'E-Tiket Festival' : 'E-Tiket Peserta' }})
                        </div>
                        <div class="pass-id-number">
                            {{ $participant->registration_number ?: ('PASS-' . $participant->id) }}
                        </div>
                        @if(count($specItems) > 0)
                            <table class="specs-mini-table" @if($isFestival) style="color: #0c4a6e; border-top-color: #7dd3fc;" @endif>
                                <tr>
                                    @foreach($specItems as $item)
                                        <td>{{ $item }}</td>
                                    @endforeach
                                </tr>
                            </table>
                        @endif
                        @if($participant->bib_number)
                            <div class="optional-bib-pill">
                                No. Bib / Dada (Opsional): #{{ $participant->bib_number }}
                            </div>
                        @endif
                    </div>
                @endif

                <!-- 2. Two-Column Identity & QR Verification -->
                <table class="body-grid-table">
                    <tr>
                        <!-- Left: Athlete Profile & Face Check -->
                        <td class="col-profile">
                            <div class="section-label">
                                @if($showPhotoCell)
                                    Profil Peserta &amp; Verifikasi Wajah Anti-Joki
                                @else
                                    Profil &amp; Identitas Resmi Peserta
                                @endif
                            </div>
                            <table class="profile-inner-table">
                                <tr>
                                    @if($showPhotoCell)
                                        <td class="profile-photo-cell">
                                            @if($photo_base64)
                                                <img class="athlete-photo" src="{{ $photo_base64 }}" alt="Pas Foto" />
                                            @else
                                                <div class="photo-placeholder">PAS FOTO RESMI</div>
                                            @endif
                                            <div style="font-size: 6pt; font-weight: bold; color: #64748b; text-transform: uppercase;">
                                                Cocokkan Wajah Atlet
                                            </div>
                                        </td>
                                    @endif
                                    <td class="profile-detail-cell">
                                        <h2 class="player-name">{{ $participant->full_name }}</h2>
                                        @if($participant->nickname)
                                            <div class="player-nickname">"{{ $participant->nickname }}"</div>
                                        @endif
                                        <div class="identity-line identity-muted">
                                            NIK: {{ substr((string) $participant->nik, 0, 6) . '******' . substr((string) $participant->nik, -4) }}
                                        </div>
                                        @if(! empty($presets['team_origin']))
                                            <div class="identity-line">
                                                <strong>Asal SSB / Klub:</strong><br />
                                                {{ $participant->current_club_ssb ?: 'Peserta Mandiri / Independen' }}
                                            </div>
                                        @endif
                                        <div class="identity-line">
                                            <strong>Tanggal Lahir:</strong><br />
                                            {{ $participant->birth_date?->format('d/m/Y') }} ({{ $participant->birth_date?->age }} Thn)
                                        </div>
                                        @if(! empty($presets['body_measurements']) && ($participant->height_cm || $participant->weight_kg))
                                            <div class="identity-line">
                                                <strong>Tinggi / Berat:</strong>
                                                {{ $participant->height_cm ? $participant->height_cm . ' cm' : '-' }} / {{ $participant->weight_kg ? $participant->weight_kg . ' kg' : '-' }}
                                            </div>
                                        @endif
                                        @if(! empty($presets['experience']) && ! empty($participant->playing_experience))
                                            <div class="identity-line">
                                                <strong>Pengalaman:</strong> {{ \Illuminate\Support\Str::limit($participant->playing_experience, 70) }}
                                            </div>
                                        @endif
                                        @if($session)
                                            <div class="identity-line">
                                                <strong>Sesi:</strong> {{ $session->session_name }}
                                            </div>
                                        @endif
                                        @if(count($customItems) > 0)
                                            @foreach(array_slice($customItems, 0, 4) as $cItem)
                                                <div class="identity-line">
                                                    <strong>{{ $cItem['label'] }}:</strong> {{ $cItem['value'] }}
                                                </div>
                                            @endforeach
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>

                        <!-- Right: Gatekeeper QR & Authenticity Signature -->
                        <td class="col-gate">
                            <img class="qr-img" src="{{ $qr_code_svg }}" alt="QR Keabsahan" />
                            <div class="reg-number">{{ $participant->registration_number }}</div>
                            <div class="gate-scan-label">Scan Gatekeeper Check-in</div>
                            <div class="sec-hash">
                                AUTH-SIG: <strong style="color: #0f172a;">{{ $auth_signature }}</strong>
                            </div>
                            <div class="seal-badge">
                                Verified Digital Pass
                            </div>
                            <div style="font-size: 6pt; color: #64748b; margin-top: 6px; line-height: 1.35;">
                                Pindai QR atau ketik ID Digital Pass di gerbang.<br />
                                Cocokkan kode AUTH-SIG dengan portal verifikasi.
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- 3. Authenticity Audit & Verification Trail -->
                <div class="auth-meta-box">
                    <table class="auth-meta-table">
                        <tr>
                            <td style="width: 50%;"><strong>Disahkan Oleh:</strong> {{ $verified_by_name }}</td>
                            <td style="width: 50%;"><strong>Waktu Sah:</strong> {{ $verified_at_label }}</td>
                        </tr>
                        <tr>
                            <td>
                                @if($isFree)
                                    <strong>Status Biaya:</strong> Bebas Biaya (Gratis)
                                @else
                                    <strong>No. Invoice Lunas:</strong> <span style="font-family: monospace;">{{ $invoice_number }}</span>
                                @endif
                            </td>
                            <td><strong>Masa Berlaku:</strong> {{ $valid_dates_label }}</td>
                        </tr>
                        @if(! empty($event?->contact_person) || ! empty($event?->contact_whatsapp))
                            <tr>
                                <td colspan="2">
                                    <strong>Kontak Panitia:</strong>
                                    {{ $event?->contact_person ?: 'Panitia Penyelenggara' }}@if(! empty($event?->contact_whatsapp)) ({{ $event->contact_whatsapp }})@endif
                                </td>
                            </tr>
                        @endif
                    </table>
                </div>

                @if(count($stagesList) > 0)
                    <div class="stages-box">
                        <div class="stages-title">Jadwal Tahapan Kegiatan:</div>
                        <table class="stages-table">
                            @foreach(array_slice($stagesList, 0, 3) as $stageItem)
                                <tr>
                                    <td style="width: 52%; font-weight: bold; color: #0f172a;">
                                        {{ $stageItem['stage_name'] }}
                                    </td>
                                    <td style="width: 48%; color: #475569; text-align: right;">
                                        {{ $stageItem['date_label'] }}@if(! empty($stageItem['time_label'])) &bull; {{ $stageItem['time_label'] }}@endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif

                <div class="ticket-divider"></div>

                <!-- 4. Gatekeeper Check-in Rules -->
                <div class="rules-box">
                    <div class="rules-title">Petunjuk Check-in Gatekeeper:</div>
                    <div>1. Tunjukkan QR Code atau ID Digital Pass ini (cetak / layar smartphone) ke petugas gerbang.</div>
                    @if(count($docLabels) > 0)
                        <div>2. Wajib membawa dokumen asli kelengkapan event ({{ implode(', ', $docLabels) }}) untuk pencocokan.</div>
                    @else
                        <div>2. Wajib membawa dokumen identitas asli peserta yang sah untuk pencocokan gatekeeper.</div>
                    @endif
                    <div>3. Hadir di lokasi kegiatan minimal {{ $cutoffMins }} menit sebelum sesi atau tahapan dimulai.</div>
                    <div>4. Mengenakan perlengkapan {{ $isClinic ? 'latihan olahraga' : 'olahraga lengkap' }} dan mematuhi tata tertib resmi penyelenggara.</div>
                </div>
            </div>
        </div>

        <!-- Footer Bar -->
        <table class="footer-table">
            <tr>
                <td class="footer-left">
                    Persija Development Digital Pass &bull; {{ $generated_at }}
                </td>
                <td class="footer-right">
                    Pemalsuan pass digital akan ditindak sesuai regulasi resmi.
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
