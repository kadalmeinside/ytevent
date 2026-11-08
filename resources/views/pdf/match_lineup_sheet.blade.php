<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Susunan Pemain (DSP) - {{ $team?->name }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }
        .header-box {
            border-bottom: 2px solid #ea580c;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .title {
            font-size: 15px;
            font-weight: bold;
            color: #dc2626;
            text-transform: uppercase;
            margin: 0;
        }
        .subtitle {
            font-size: 11px;
            font-weight: bold;
            color: #1e293b;
            margin-top: 2px;
        }
        .match-banner {
            background-color: #fff7ed;
            border: 1px solid #fdba74;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 12px;
        }
        .section-title {
            font-size: 10.5px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #f1f5f9;
            padding: 5px 8px;
            border-left: 4px solid #ea580c;
            margin: 10px 0 6px 0;
        }
        .dsp-table {
            width: 100%;
            border-collapse: collapse;
        }
        .dsp-table th {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
            font-size: 9.5px;
            color: #334155;
        }
        .dsp-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            font-size: 9.5px;
        }
        .sign-table {
            width: 100%;
            margin-top: 24px;
            border-collapse: collapse;
        }
        .sign-line {
            margin-top: 42px;
            border-bottom: 1px solid #334155;
            width: 160px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="header-box">
        <div class="title">DAFTAR SUSUNAN PEMAIN RESMI (DIGITAL MATCH LINEUP / DSP)</div>
        <div class="subtitle">{{ $event?->name }} &bull; Kategori {{ $event?->age_category }}</div>
    </div>

    <div class="match-banner">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div><strong>Pertandingan #{{ $match->match_number }}</strong> ({{ \App\Models\TournamentMatch::stageLabel($match->stage) }})</div>
                    <div><strong>Tim:</strong> {{ $team?->name }} (Warna Jersey: {{ $team?->home_jersey_color ?: 'Utama' }} / GK: {{ $team?->gk_jersey_color ?: 'Kiper' }})</div>
                    <div><strong>Lawan:</strong> {{ $opponent?->name ?? 'TBD' }}</div>
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right;">
                    <div><strong>Waktu Kick-Off:</strong> {{ $match->kickoff_at ? $match->kickoff_at->translatedFormat('d M Y, H:i') . ' WIB' : 'Sesuai Jadwal' }}</div>
                    <div><strong>Lapangan:</strong> {{ $match->pitch_name ?: 'Lapangan Utama' }}</div>
                    <div><strong>Pelatih Kepala:</strong> {{ $team?->head_coach_name ?: '-' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">PEMAIN UTAMA (STARTING LINEUP &bull; {{ count($starting_players) }} PEMAIN)</div>
    <table class="dsp-table">
        <thead>
            <tr>
                <th style="width: 8%;">No. Punggung</th>
                <th style="width: 10%;">Posisi</th>
                <th style="width: 38%;">Nama Lengkap Pemain</th>
                <th style="width: 22%;">Kode Akreditasi</th>
                <th style="width: 10%;">Kapten</th>
                <th style="width: 12%;">Cek Wasit</th>
            </tr>
        </thead>
        <tbody>
            @forelse($starting_players as $entry)
                <tr>
                    <td style="font-weight: bold; text-align: center;">#{{ $entry->jersey_number_snapshot ?? $entry->player?->jersey_number }}</td>
                    <td style="font-weight: bold;">{{ $entry->position_snapshot ?? $entry->player?->position }}</td>
                    <td style="font-weight: bold;">{{ $entry->player?->full_name }}</td>
                    <td style="font-family: monospace; font-size: 8.5px;">{{ $entry->player?->accreditation_code }}</td>
                    <td style="text-align: center; font-weight: bold; color: #ea580c;">{{ $entry->is_captain ? 'KAPTEN (C)' : '-' }}</td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #94a3b8;">Belum ada pemain utama dipilih.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">PEMAIN CADANGAN (SUBSTITUTES &bull; {{ count($substitute_players) }} PEMAIN)</div>
    <table class="dsp-table">
        <thead>
            <tr>
                <th style="width: 8%;">No. Punggung</th>
                <th style="width: 10%;">Posisi</th>
                <th style="width: 38%;">Nama Lengkap Pemain</th>
                <th style="width: 22%;">Kode Akreditasi</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 12%;">Cek Wasit</th>
            </tr>
        </thead>
        <tbody>
            @forelse($substitute_players as $entry)
                <tr>
                    <td style="font-weight: bold; text-align: center;">#{{ $entry->jersey_number_snapshot ?? $entry->player?->jersey_number }}</td>
                    <td>{{ $entry->position_snapshot ?? $entry->player?->position }}</td>
                    <td>{{ $entry->player?->full_name }}</td>
                    <td style="font-family: monospace; font-size: 8.5px;">{{ $entry->player?->accreditation_code }}</td>
                    <td>Cadangan</td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #94a3b8;">Belum ada pemain cadangan dipilih.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="sign-table">
        <tr>
            <td style="width: 50%; text-align: center;">
                <div>Pelatih Kepala / Manajer Tim {{ $team?->name }},</div>
                <div class="sign-line"></div>
                <div style="font-weight: bold; margin-top: 4px;">{{ $team?->head_coach_name ?: $team?->manager_name }}</div>
            </td>
            <td style="width: 50%; text-align: center;">
                <div>Pengawas Pertandingan (Match Commissioner),</div>
                <div class="sign-line"></div>
                <div style="font-weight: bold; margin-top: 4px;">Panitia Pelaksana</div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 14px; border-top: 1px solid #e2e8f0; padding-top: 4px; font-size: 8px; color: #94a3b8; text-align: right;">
        Dicetak dari Sistem Digital Match Lineup Persija Development pada {{ $generated_at }}
    </div>
</body>
</html>

