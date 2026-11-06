<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Album Roster Pemain - {{ $team->name }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 12mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.3;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #ea580c;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .brand-title {
            font-size: 18px;
            font-weight: bold;
            color: #dc2626;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .tournament-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e293b;
            margin: 2px 0;
        }
        .meta-text {
            font-size: 10px;
            color: #64748b;
        }
        .team-badge {
            background-color: #fff7ed;
            border: 1px solid #fdba74;
            border-radius: 6px;
            padding: 6px 12px;
            text-align: right;
        }
        .team-name {
            font-size: 15px;
            font-weight: bold;
            color: #c2410c;
            margin: 0;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            background-color: #f1f5f9;
            padding: 4px 8px;
            border-left: 4px solid #ea580c;
            margin: 10px 0 8px 0;
        }
        .player-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .player-card-td {
            width: 25%;
            padding: 4px;
            vertical-align: top;
        }
        .player-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 6px;
            background-color: #ffffff;
            height: 128px;
            box-sizing: border-box;
        }
        .player-card-inner {
            width: 100%;
        }
        .player-photo {
            width: 58px;
            height: 72px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #94a3b8;
            background-color: #f8fafc;
        }
        .player-photo-placeholder {
            width: 58px;
            height: 72px;
            border-radius: 4px;
            border: 1px dashed #cbd5e1;
            background-color: #f8fafc;
            text-align: center;
            line-height: 72px;
            font-size: 9px;
            color: #94a3b8;
        }
        .jersey-badge {
            display: inline-block;
            background-color: #ea580c;
            color: #ffffff;
            font-weight: bold;
            font-size: 11px;
            padding: 1px 6px;
            border-radius: 3px;
        }
        .pos-badge {
            display: inline-block;
            background-color: #0284c7;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            padding: 1px 4px;
            border-radius: 3px;
            margin-left: 3px;
        }
        .player-name {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 3px;
            margin-bottom: 2px;
        }
        .player-sub {
            font-size: 9px;
            color: #475569;
            line-height: 1.25;
        }
        .qr-cell {
            text-align: right;
            vertical-align: top;
            width: 44px;
        }
        .qr-img {
            width: 42px;
            height: 42px;
        }
        .officials-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .officials-table th {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            font-size: 10px;
            text-align: left;
            color: #334155;
        }
        .officials-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            font-size: 10px;
        }
        .signature-table {
            width: 100%;
            margin-top: 14px;
            page-break-inside: avoid;
        }
        .signature-box {
            width: 32%;
            text-align: center;
            vertical-align: top;
            font-size: 10px;
        }
        .signature-line {
            margin-top: 45px;
            border-bottom: 1px solid #334155;
            display: inline-block;
            width: 180px;
        }
        .watermark-footer {
            margin-top: 10px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            font-size: 8px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 60px; vertical-align: middle;">
                @if($team_logo_base64)
                    <img src="{{ $team_logo_base64 }}" style="width: 50px; height: 50px; object-fit: contain;" alt="Logo Tim">
                @else
                    <div style="width: 50px; height: 50px; background-color: #ea580c; border-radius: 6px; text-align: center; line-height: 50px; color: #fff; font-weight: bold; font-size: 16px;">
                        {{ strtoupper(substr($team->name, 0, 2)) }}
                    </div>
                @endif
            </td>
            <td style="vertical-align: middle; padding-left: 10px;">
                <div class="brand-title">PERSIJA DEVELOPMENT</div>
                <div class="tournament-title">{{ $event->name }} &bull; Kategori {{ $event->age_category }}</div>
                <div class="meta-text">Venue: {{ $event->venue_name ?? 'Stadion Resmi' }} &bull; Format: {{ ucfirst($event->format) }}</div>
            </td>
            <td style="vertical-align: middle; text-align: right;">
                <div class="team-badge">
                    <div class="team-name">{{ $team->name }}</div>
                    <div class="meta-text">Asal: {{ $team->origin_association }} &bull; Status: TERKUNCI RESMI</div>
                    <div class="meta-text">Pengesahan: {{ $locked_at }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Skuad Pemain Sah -->
    <div class="section-title">DAFTAR PEMAIN SAH (TEAM ROSTER &bull; {{ count($players) }} PEMAIN)</div>

    <table class="player-grid">
        @foreach(collect($players)->chunk(4) as $row)
            <tr>
                @foreach($row as $player)
                    <td class="player-card-td">
                        <div class="player-card">
                            <table class="player-card-inner">
                                <tr>
                                    <td style="width: 60px; vertical-align: top;">
                                        @if(!empty($player['photo_base64']))
                                            <img src="{{ $player['photo_base64'] }}" class="player-photo" alt="{{ $player['full_name'] }}">
                                        @else
                                            <div class="player-photo-placeholder">NO PHOTO</div>
                                        @endif
                                    </td>
                                    <td style="vertical-align: top; padding-left: 6px;">
                                        <div>
                                            <span class="jersey-badge">#{{ $player['jersey_number'] }}</span>
                                            <span class="pos-badge">{{ $player['position'] }}</span>
                                        </div>
                                        <div class="player-name" title="{{ $player['full_name'] }}">{{ $player['full_name'] }}</div>
                                        <div class="player-sub">TTL: {{ $player['birth_date'] }}</div>
                                        <div class="player-sub">NIK: {{ substr($player['nik'], 0, 6) }}...{{ substr($player['nik'], -3) }}</div>
                                        <div class="player-sub" style="font-size: 8px; color: #ea580c; font-family: monospace;">ID: {{ $player['accreditation_code'] }}</div>
                                    </td>
                                    <td class="qr-cell">
                                        @if(!empty($player['qr_code_svg']))
                                            <img src="{{ $player['qr_code_svg'] }}" class="qr-img" alt="QR Verifikasi">
                                            <div style="font-size: 7px; color: #64748b; text-align: center;">SCAN</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                @endforeach
                @for($i = count($row); $i < 4; $i++)
                    <td class="player-card-td"></td>
                @endfor
            </tr>
        @endforeach
    </table>

    <!-- Ofisial Tim -->
    <div class="section-title" style="margin-top: 10px;">OFISIAL TIM & TIM PELATIH</div>
    <table class="officials-table">
        <thead>
            <tr>
                <th style="width: 25%;">Nama Lengkap</th>
                <th style="width: 25%;">Jabatan / Peran</th>
                <th style="width: 25%;">Lisensi Pelatih</th>
                <th style="width: 25%;">Status Verifikasi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($officials as $official)
                <tr>
                    <td style="font-weight: bold;">{{ $official['full_name'] }}</td>
                    <td>{{ $official['role'] }}</td>
                    <td>{{ $official['license_number'] }}</td>
                    <td style="color: #16a34a; font-weight: bold;">TERVERIFIKASI SAH</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #94a3b8;">Belum ada data ofisial terdaftar.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Kolom Pengesahan & Tanda Tangan -->
    <table class="signature-table">
        <tr>
            <td class="signature-box">
                <div>Manajer Tim,</div>
                <div class="signature-line"></div>
                <div style="font-weight: bold; margin-top: 4px;">{{ $team->manager_name }}</div>
                <div style="color: #64748b; font-size: 9px;">{{ $team->name }}</div>
            </td>
            <td class="signature-box">
                <div>Pelatih Kepala,</div>
                <div class="signature-line"></div>
                <div style="font-weight: bold; margin-top: 4px;">{{ $team->head_coach_name ?? 'Pelatih Kepala' }}</div>
                <div style="color: #64748b; font-size: 9px;">Head Coach</div>
            </td>
            <td class="signature-box">
                <div>Pengawas Pertandingan / Panitia,</div>
                <div class="signature-line"></div>
                <div style="font-weight: bold; margin-top: 4px;">Panitia Pelaksana</div>
                <div style="color: #64748b; font-size: 9px;">Persija Development</div>
            </td>
        </tr>
    </table>

    <div class="watermark-footer">
        Dicetak secara resmi melalui Sistem Akreditasi Persija Development pada {{ $generated_at }}. Dokumen ini sah dan mengikat untuk seluruh pertandingan resmi turnamen.
    </div>
</body>
</html>
