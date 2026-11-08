<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Terjadi Kesalahan') — Persija Development</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: radial-gradient(circle at 50% 0%, #1e130c 0%, #0c0a09 100%);
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: rgba(28, 25, 23, 0.75);
            border: 1px solid rgba(234, 88, 12, 0.25);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 1.25rem;
            padding: 2.5rem 2rem;
            max-width: 30rem;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 40px -10px rgba(234, 88, 12, 0.15);
        }
        .badge {
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.875rem;
            font-weight: 700;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            background: rgba(234, 88, 12, 0.15);
            color: #fb923c;
            border: 1px solid rgba(234, 88, 12, 0.3);
            margin-bottom: 1.25rem;
            letter-spacing: 0.05em;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1.25;
            color: #ffffff;
            margin-bottom: 0.75rem;
        }
        p {
            color: #94a3b8;
            font-size: 0.9375rem;
            line-height: 1.6;
            margin-bottom: 1.75rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #ea580c 0%, #dc2626 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.875rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(234, 88, 12, 0.35);
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(234, 88, 12, 0.5);
        }
        .footer-note {
            margin-top: 2rem;
            font-size: 0.75rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">@yield('code', 'ERROR')</div>
        <h1>@yield('heading', 'Terjadi Kesalahan')</h1>
        <p>@yield('message', 'Halaman atau tindakan yang Anda minta tidak dapat diproses saat ini.')</p>
        <a href="{{ url('/') }}" class="btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            Kembali ke Beranda
        </a>
        <div class="footer-note">
            Persija Development &bull; Tournament & League Management
        </div>
    </div>
</body>
</html>
