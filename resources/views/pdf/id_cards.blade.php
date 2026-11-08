<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Akreditasi (ID Cards) - {{ $team->name }}</title>
    <style>
        /* Kertas A4 Portrait: 210mm x 297mm, dibagi 4 kuadran presisi (2x2 = 4 ID per halaman) */
        @page {
            size: a4 portrait;
            margin: 6mm;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #0f172a;
            background-color: #ffffff;
        }
        .page-break {
            page-break-after: always;
        }
        /* Area Cetak Bersih di dalam A4: Lebar 198mm x Tinggi 284mm -> 2 Kolom (99mm) x 2 Baris (140mm) */
        .quadrant-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0;
            padding: 0;
            page-break-inside: avoid;
        }
        .quadrant-cell {
            width: 50%;
            height: 138mm;
            vertical-align: top;
            padding: 3.5mm;
            margin: 0;
        }
        /* Garis potong presisi tengah A4 (lipat/potong 4 sama rata) */
        .cell-tl {
            border-right: 0.8pt dashed #94a3b8;
            border-bottom: 0.8pt dashed #94a3b8;
        }
        .cell-tr {
            border-bottom: 0.8pt dashed #94a3b8;
        }
        .cell-bl {
            border-right: 0.8pt dashed #94a3b8;
        }
        .cell-br {
            /* Kuadran kanan bawah */
        }
        /* Bingkai Kartu Akreditasi di dalam tiap kuadran */
        .id-card {
            width: 100%;
            height: 130mm;
            border: 1.5pt solid #cbd5e1;
            border-radius: 8pt;
            overflow: hidden;
            background-color: #ffffff;
            position: relative;
        }
        .lanyard-slot {
            width: 15mm;
            height: 2.8mm;
            margin: 2mm auto 1.5mm auto;
            border-radius: 2mm;
            border: 1pt solid #cbd5e1;
            background-color: #f8fafc;
        }
        .card-header {
            background-color: #ea580c;
            color: #ffffff;
            padding: 2.5mm 3mm;
            text-align: center;
            border-bottom: 1.5pt solid #c2410c;
        }
        .card-brand {
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: 1pt;
            text-transform: uppercase;
            color: #ffedd5;
            margin-bottom: 0.8mm;
        }
        .card-tournament {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            line-height: 1.2;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .card-category {
            font-size: 7.5pt;
            font-weight: bold;
            margin-top: 0.8mm;
            color: #fff7ed;
        }
        .role-strip {
            padding: 1.5mm 2mm;
            text-align: center;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 0.8pt;
            text-transform: uppercase;
        }
        .role-player {
            background-color: #fee2e2;
            color: #b91c1c;
            border-bottom: 1pt solid #fecaca;
        }
        .role-official {
            background-color: #e0f2fe;
            color: #0369a1;
            border-bottom: 1pt solid #bae6fd;
        }
        .card-body {
            padding: 3mm 3.5mm 1.5mm 3.5mm;
            text-align: center;
        }
        .photo-wrapper {
            margin: 0 auto 2mm auto;
            width: 29mm;
            height: 36mm;
            border-radius: 5pt;
            overflow: hidden;
            border: 1.5pt solid #94a3b8;
            background-color: #f8fafc;
        }
        .card-photo {
            width: 29mm;
            height: 36mm;
            object-fit: cover;
            display: block;
        }
        .photo-placeholder {
            width: 29mm;
            height: 36mm;
            line-height: 36mm;
            text-align: center;
            font-size: 8pt;
            font-weight: bold;
            color: #94a3b8;
        }
        .jersey-badge {
            display: inline-block;
            background-color: #ea580c;
            color: #ffffff;
            font-size: 9.5pt;
            font-weight: bold;
            padding: 0.8mm 3mm;
            border-radius: 9pt;
            margin-bottom: 1.2mm;
        }
        .holder-name {
            font-size: 10.5pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0.5mm 0 0.8mm 0;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .holder-team {
            font-size: 8.5pt;
            font-weight: bold;
            color: #c2410c;
            margin-bottom: 0.8mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .holder-meta {
            font-size: 7.5pt;
            color: #475569;
            line-height: 1.25;
        }
        .card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 19.5mm;
            background-color: #f8fafc;
            border-top: 1pt solid #e2e8f0;
            padding: 1.8mm 3mm;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .code-label {
            font-size: 6pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.3pt;
        }
        .code-text {
            font-family: 'Courier New', Courier, monospace;
            font-size: 7.5pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0.4mm 0;
        }
        .status-verified {
            font-size: 7pt;
            font-weight: bold;
            color: #15803d;
            letter-spacing: 0.2pt;
        }
        .qr-box {
            width: 15mm;
            height: 15mm;
            background-color: #ffffff;
            border: 0.8pt solid #cbd5e1;
            border-radius: 3pt;
            padding: 0.8mm;
            display: inline-block;
        }
        .qr-card-img {
            width: 13mm;
            height: 13mm;
            display: block;
        }
    </style>
</head>
<body>
    @php
        $pages = collect($cards)->chunk(4)->values();
    @endphp

    @foreach($pages as $pageIndex => $pageCards)
        @php
            $items = $pageCards->values();
            $quadrants = [
                [0, 'cell-tl', $items->get(0)],
                [1, 'cell-tr', $items->get(1)],
                [2, 'cell-bl', $items->get(2)],
                [3, 'cell-br', $items->get(3)],
            ];
        @endphp
        <table class="quadrant-table {{ !$loop->last ? 'page-break' : '' }}">
            @foreach(collect($quadrants)->chunk(2) as $rowQuadrants)
                <tr>
                    @foreach($rowQuadrants as $q)
                        @php
                            $cellClass = $q[1];
                            $card = $q[2];
                        @endphp
                        <td class="quadrant-cell {{ $cellClass }}">
                            @if($card)
                                <div class="id-card">
                                    <!-- Lubang Lanyard Presisi Tengah -->
                                    <div class="lanyard-slot"></div>

                                    <!-- Header Kompetisi -->
                                    <div class="card-header">
                                        <div class="card-brand">PERSIJA DEVELOPMENT</div>
                                        <div class="card-tournament">{{ $event->name }}</div>
                                        <div class="card-category">KATEGORI {{ strtoupper($event->age_category) }}</div>
                                    </div>

                                    <!-- Strip Peran -->
                                    <div class="role-strip {{ $card['type'] === 'player' ? 'role-player' : 'role-official' }}">
                                        {{ $card['role_label'] }}
                                    </div>

                                    <!-- Badan Kartu -->
                                    <div class="card-body">
                                        <div class="photo-wrapper">
                                            @if(!empty($card['photo_base64']))
                                                <img src="{{ $card['photo_base64'] }}" class="card-photo" alt="{{ $card['name'] }}">
                                            @else
                                                <div class="photo-placeholder">FOTO RESMI</div>
                                            @endif
                                        </div>

                                        @if($card['type'] === 'player' && !empty($card['jersey_number']))
                                            <div>
                                                <span class="jersey-badge">#{{ $card['jersey_number'] }} &bull; {{ $card['role'] }}</span>
                                            </div>
                                        @else
                                            <div>
                                                <span class="jersey-badge" style="background-color: #0284c7;">{{ strtoupper($card['role']) }}</span>
                                            </div>
                                        @endif

                                        <div class="holder-name">{{ $card['name'] }}</div>
                                        <div class="holder-team">{{ $team->name }}</div>
                                        <div class="holder-meta">
                                            @if($card['type'] === 'player')
                                                Asal Tim: {{ $team->origin_association ?: '-' }}
                                            @else
                                                @if(!empty($card['license_number']))
                                                    <span style="font-weight: bold; color: #0369a1;">Lisensi: {{ $card['license_number'] }}</span>
                                                @else
                                                    Ofisial Tim Terdaftar
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Footer QR & Kode Akreditasi -->
                                    <div class="card-footer">
                                        <table class="footer-table">
                                            <tr>
                                                <td style="vertical-align: middle; text-align: left;">
                                                    <div class="code-label">KODE AKREDITASI RESMI</div>
                                                    <div class="code-text">{{ $card['accreditation_code'] }}</div>
                                                    <div class="status-verified">TERVERIFIKASI SAH</div>
                                                </td>
                                                <td style="vertical-align: middle; text-align: right; width: 17mm;">
                                                    @if(!empty($card['qr_code_svg']))
                                                        <div class="qr-box">
                                                            <img src="{{ $card['qr_code_svg'] }}" class="qr-card-img" alt="QR">
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endforeach
</body>
</html>
