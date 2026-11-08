<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Album Roster Pemain &amp; Ofisial - {{ $team->name }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 10mm 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
            font-size: 10.5px;
            line-height: 1.3;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #ea580c;
            padding-bottom: 7px;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        .brand-title {
            font-size: 17px;
            font-weight: bold;
            color: #dc2626;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .tournament-title {
            font-size: 13px;
            font-weight: bold;
            color: #1e293b;
            margin: 2px 0;
        }
        .meta-text {
            font-size: 9.5px;
            color: #64748b;
        }
        .team-badge {
            background-color: #fff7ed;
            border: 1px solid #fdba74;
            border-radius: 6px;
            padding: 6px 12px;
            text-align: right;
            display: inline-block;
        }
        .team-name {
            font-size: 14px;
            font-weight: bold;
            color: #c2410c;
            margin: 0;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            background-color: #f1f5f9;
            padding: 5px 8px;
            border-left: 4px solid #ea580c;
            margin: 8px 0 6px 0;
        }
        /* Grid Presisi 3 Kartu Per Baris (33.3333% x 3 = 100%) */
        .player-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .player-card-td {
            width: 33.3333%;
            padding: 4px;
            vertical-align: top;
        }
        .player-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 7px 8px;
            background-color: #ffffff;
            height: 92px;
            box-sizing: border-box;
        }
        .player-card-inner {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .player-photo {
            width: 58px;
            height: 76px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #94a3b8;
            background-color: #f8fafc;
            display: block;
        }
        .player-photo-placeholder {
            width: 58px;
            height: 76px;
            border-radius: 4px;
            border: 1px dashed #cbd5e1;
            background-color: #f8fafc;
            text-align: center;
            line-height: 76px;
            font-size: 8.5px;
            font-weight: bold;
            color: #94a3b8;
        }
        .jersey-badge {
            display: inline-block;
            background-color: #ea580c;
            color: #ffffff;
            font-weight: bold;
            font-size: 10.5px;
            padding: 1px 6px;
            border-radius: 3px;
        }
        .pos-badge {
            display: inline-block;
            background-color: #0284c7;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            padding: 1px 5px;
            border-radius: 3px;
            margin-left: 3px;
        }
        .official-role-badge {
            display: inline-block;
            background-color: #0369a1;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            padding: 1.5px 6px;
            border-radius: 3px;
            text-transform: uppercase;
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
            line-height: 1.28;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .qr-cell {
            text-align: right;
            vertical-align: middle;
            width: 52px;
        }
        .qr-img {
            width: 46px;
            height: 46px;
            display: inline-block;
        }
        .signature-block {
            margin-top: 10px;
            page-break-inside: avoid;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 8px;
        }
        .signature-box {
            width: 33.3333%;
            text-align: center;
            vertical-align: top;
            font-size: 9.5px;
        }
        .signature-line {
            margin-top: 40px;
            border-bottom: 1px solid #334155;
            display: inline-block;
            width: 180px;
        }
        .watermark-footer {
            margin-top: 8px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            font-size: 8px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>
<body>
    <!-- Satu Header Tunggal (Tidak Double) -->
    <table class="header-table">
        <tr>
            <td style="width: 56px; vertical-align: middle;">
                @if($team_logo_base64)
                    <img src="{{ $team_logo_base64 }}" style="width: 48px; height: 48px; object-fit: contain;" alt="Logo Tim">
                @else
                    <div style="width: 48px; height: 48px; background-color: #ea580c; border-radius: 6px; text-align: center; line-height: 48px; color: #fff; font-weight: bold; font-size: 15px;">
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

    <!-- 1. Baris Daftar Pemain Sah (3 Pemain Per Baris Rata Presisi) -->
    <div class="section-title">DAFTAR PEMAIN SAH (TEAM ROSTER &bull; {{ count($players) }} PEMAIN)</div>

    <table class="player-grid">
        @foreach(collect($players)->chunk(3) as $row)
            <tr>
                @foreach($row as $player)
                    <td class="player-card-td">
                        <div class="player-card">
                            <table class="player-card-inner">
                                <tr>
                                    <td style="width: 62px; vertical-align: top;">
                                        @if(!empty($player['photo_base64']))
                                            <img src="{{ $player['photo_base64'] }}" class="player-photo" alt="{{ $player['full_name'] }}">
                                        @else
                                            <div class="player-photo-placeholder">NO PHOTO</div>
                                        @endif
                                    </td>
                                    <td style="vertical-align: top; padding-left: 8px; padding-right: 4px;">
                                        <div>
                                            <span class="jersey-badge">#{{ $player['jersey_number'] }}</span>
                                            <span class="pos-badge">{{ $player['position'] }}</span>
                                        </div>
                                        <div class="player-name" title="{{ $player['full_name'] }}">{{ $player['full_name'] }}</div>
                                        <div class="player-sub">TTL: {{ $player['birth_place'] ? $player['birth_place'] . ', ' : '' }}{{ $player['birth_date'] }}</div>
                                        <div class="player-sub">NIK: {{ substr($player['nik'], 0, 6) }}...{{ substr($player['nik'], -4) }}</div>
                                        <div class="player-sub" style="font-size: 8px; color: #ea580c; font-family: monospace; font-weight: bold;">ID: {{ $player['accreditation_code'] }}</div>
                                    </td>
                                    <td class="qr-cell">
                                        @if(!empty($player['qr_code_svg']))
                                            <img src="{{ $player['qr_code_svg'] }}" class="qr-img" alt="QR Verifikasi">
                                            <div style="font-size: 6.5px; color: #64748b; text-align: center; font-weight: bold; margin-top: 1px;">VERIFIKASI</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                @endforeach
                @for($i = count($row); $i < 3; $i++)
                    <td class="player-card-td"></td>
                @endfor
            </tr>
        @endforeach
    </table>

    <!-- 2. Baris Baru: Daftar Ofisial Tim & Tim Pelatih (Tanpa Tabel Double) -->
    <div class="section-title" style="margin-top: 10px;">OFISIAL TIM &amp; TIM PELATIH RESMI ({{ count($officials) }} OFISIAL)</div>

    <table class="player-grid">
        @forelse(collect($officials)->chunk(3) as $officialRow)
            <tr>
                @foreach($officialRow as $official)
                    <td class="player-card-td">
                        <div class="player-card">
                            <table class="player-card-inner">
                                <tr>
                                    <td style="width: 62px; vertical-align: top;">
                                        @if(!empty($official['photo_base64']))
                                            <img src="{{ $official['photo_base64'] }}" class="player-photo" alt="{{ $official['full_name'] }}">
                                        @else
                                            <div class="player-photo-placeholder">FOTO</div>
                                        @endif
                                    </td>
                                    <td style="vertical-align: top; padding-left: 8px; padding-right: 4px;">
                                        <div>
                                            <span class="official-role-badge">{{ $official['role'] }}</span>
                                        </div>
                                        <div class="player-name" title="{{ $official['full_name'] }}">{{ $official['full_name'] }}</div>
                                        <div class="player-sub">Lisensi: <strong>{{ $official['license_number'] ?? '-' }}</strong></div>
                                        <div class="player-sub" style="color: #16a34a; font-weight: bold;">Status: TERVERIFIKASI SAH</div>
                                        @if(!empty($official['accreditation_code']))
                                            <div class="player-sub" style="font-size: 8px; color: #0369a1; font-family: monospace; font-weight: bold;">ID: {{ $official['accreditation_code'] }}</div>
                                        @endif
                                    </td>
                                    <td class="qr-cell">
                                        @if(!empty($official['qr_code_svg']))
                                            <img src="{{ $official['qr_code_svg'] }}" class="qr-img" alt="QR Ofisial">
                                            <div style="font-size: 6.5px; color: #64748b; text-align: center; font-weight: bold; margin-top: 1px;">VERIFIKASI</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                @endforeach
                @for($i = count($officialRow); $i < 3; $i++)
                    <td class="player-card-td"></td>
                @endfor
            </tr>
        @empty
            <tr>
                <td colspan="3" style="padding: 8px; text-align: center; color: #94a3b8; border: 1px dashed #cbd5e1; border-radius: 6px;">
                    Belum ada data ofisial terdaftar.
                </td>
            </tr>
        @endforelse
    </table>

    <!-- 3. Baris Baru: Persetujuan & Tanda Tangan Resmi -->
    <div class="signature-block">
        <div class="section-title">PERSETUJUAN &amp; TANDA TANGAN RESMI PERTANDINGAN</div>
        <table class="signature-table">
            <tr>
                <td class="signature-box">
                    <div>Manajer Tim,</div>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold; margin-top: 4px;">{{ $team->manager_name }}</div>
                    <div style="color: #64748b; font-size: 8.5px;">{{ $team->name }}</div>
                </td>
                <td class="signature-box">
                    <div>Pelatih Kepala,</div>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold; margin-top: 4px;">{{ $team->head_coach_name ?? 'Pelatih Kepala' }}</div>
                    <div style="color: #64748b; font-size: 8.5px;">Head Coach</div>
                </td>
                <td class="signature-box">
                    <div>Pengawas Pertandingan / Panitia,</div>
                    <div class="signature-line"></div>
                    <div style="font-weight: bold; margin-top: 4px;">Panitia Pelaksana</div>
                    <div style="color: #64748b; font-size: 8.5px;">Persija Development</div>
                </td>
            </tr>
        </table>

        <div class="watermark-footer">
            Dicetak secara resmi melalui Sistem Akreditasi Persija Development pada {{ $generated_at }}. Dokumen ini sah dan mengikat untuk seluruh pertandingan resmi turnamen.
        </div>
    </div>
</body>
</html>
