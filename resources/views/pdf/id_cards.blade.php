<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Akreditasi (ID Cards) - {{ $team->name }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #0f172a;
        }
        .cards-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px;
        }
        .card-cell {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }
        .id-card {
            border: 1.5px dashed #94a3b8;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
            height: 380px;
            box-sizing: border-box;
            position: relative;
        }
        .card-header {
            background-color: #ea580c;
            color: #ffffff;
            padding: 10px 12px;
            text-align: center;
        }
        .card-tournament {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .card-category {
            font-size: 9px;
            opacity: 0.9;
            margin-top: 2px;
        }
        .role-strip {
            padding: 4px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .role-player {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .role-official {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        .card-body {
            padding: 12px;
            text-align: center;
        }
        .photo-container {
            margin: 0 auto 8px auto;
            width: 90px;
            height: 115px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #cbd5e1;
            background-color: #f8fafc;
        }
        .card-photo {
            width: 90px;
            height: 115px;
            object-fit: cover;
        }
        .photo-placeholder {
            width: 90px;
            height: 115px;
            line-height: 115px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }
        .jersey-circle {
            display: inline-block;
            background-color: #ea580c;
            color: #ffffff;
            font-size: 16px;
            font-weight: bold;
            width: 32px;
            height: 32px;
            line-height: 32px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 4px;
        }
        .holder-name {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin: 2px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .holder-team {
            font-size: 11px;
            font-weight: bold;
            color: #c2410c;
            margin-bottom: 2px;
        }
        .holder-pos {
            font-size: 10px;
            color: #64748b;
        }
        .card-footer {
            position: absolute;
            bottom: 8px;
            left: 12px;
            right: 12px;
            border-top: 1px solid #f1f5f9;
            padding-top: 6px;
        }
        .footer-table {
            width: 100%;
        }
        .code-text {
            font-family: monospace;
            font-size: 8px;
            color: #64748b;
            text-align: left;
        }
        .qr-card-img {
            width: 38px;
            height: 38px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @php
        $chunks = collect($cards)->chunk(4);
    @endphp

    @foreach($chunks as $chunkIndex => $pageCards)
        <table class="cards-table">
            @foreach($pageCards->chunk(2) as $row)
                <tr>
                    @foreach($row as $card)
                        <td class="card-cell">
                            <div class="id-card">
                                <!-- Card Header -->
                                <div class="card-header">
                                    <div class="card-tournament">PERSIJA &bull; {{ $event->name }}</div>
                                    <div class="card-category">Kategori Usia: {{ $event->age_category }}</div>
                                </div>

                                <!-- Role Strip -->
                                <div class="role-strip {{ $card['type'] === 'player' ? 'role-player' : 'role-official' }}">
                                    {{ $card['role_label'] }}
                                </div>

                                <!-- Card Body -->
                                <div class="card-body">
                                    <div class="photo-container">
                                        @if(!empty($card['photo_base64']))
                                            <img src="{{ $card['photo_base64'] }}" class="card-photo" alt="{{ $card['name'] }}">
                                        @else
                                            <div class="photo-placeholder">FOTO</div>
                                        @endif
                                    </div>

                                    @if($card['type'] === 'player' && !empty($card['jersey_number']))
                                        <div class="jersey-circle">#{{ $card['jersey_number'] }}</div>
                                    @endif

                                    <div class="holder-name">{{ $card['name'] }}</div>
                                    <div class="holder-team">{{ $team->name }}</div>
                                    <div class="holder-pos">
                                        @if($card['type'] === 'player')
                                            Posisi: {{ $card['role'] }}
                                        @else
                                            Jabatan: {{ $card['role'] }}
                                        @endif
                                    </div>
                                </div>

                                <!-- Card Footer with QR -->
                                <div class="card-footer">
                                    <table class="footer-table">
                                        <tr>
                                            <td style="vertical-align: middle;">
                                                <div class="code-text">ID: {{ $card['accreditation_code'] }}</div>
                                                <div class="code-text" style="color: #16a34a; font-weight: bold;">AKREDITASI SAH</div>
                                            </td>
                                            <td style="text-align: right; vertical-align: middle; width: 42px;">
                                                @if(!empty($card['qr_code_svg']))
                                                    <img src="{{ $card['qr_code_svg'] }}" class="qr-card-img" alt="QR">
                                                @else
                                                    <div style="font-size: 7px; color: #94a3b8;">OFFICIAL</div>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    @endforeach
                    @for($i = count($row); $i < 2; $i++)
                        <td class="card-cell"></td>
                    @endfor
                </tr>
            @endforeach
        </table>

        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
