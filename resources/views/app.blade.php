<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale() ?: 'id-ID') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#EA580C">
    @php
        $appName = (string) \App\Models\SystemSetting::get('app_name', config('app.name', 'Persija Development'));
        $appTagline = (string) \App\Models\SystemSetting::get('app_tagline', 'Portal Resmi Pendaftaran Turnamen & Liga Sepak Bola');
        $defaultDescription = "{$appName} — {$appTagline}. Sistem terintegrasi pendaftaran tim sepak bola, reservasi slot kuota real-time, verifikasi berkas kelayakan usia pemain, dan penerbitan ID Card akreditasi resmi.";
        $favicon = \App\Models\SystemSetting::get('app_favicon');
        $faviconUrl = $favicon ? \Illuminate\Support\Facades\Storage::disk('public')->url($favicon) : null;
        $logo = \App\Models\SystemSetting::get('app_logo');
        $logoUrl = $logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($logo) : null;
        $isPrivatePath = request()->is('admin')
            || request()->is('admin/*')
            || request()->is('manager')
            || request()->is('manager/*')
            || request()->is('private-media/*')
            || request()->is('verify/player/*');
        $robotsContent = $isPrivatePath
            ? 'noindex, nofollow, noarchive, nosnippet'
            : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
        $canonicalUrl = url()->current();
    @endphp
    <title inertia>{{ $appName }}</title>
    <meta head-key="description" name="description" content="{{ $defaultDescription }}">
    <meta head-key="robots" name="robots" content="{{ $robotsContent }}">
    <meta head-key="googlebot" name="googlebot" content="{{ $robotsContent }}">
    @if(!$isPrivatePath)
        <link head-key="canonical" rel="canonical" href="{{ $canonicalUrl }}">
        <link rel="alternate" type="text/plain" title="LLMs Context Summary" href="{{ url('/llms.txt') }}">
        <link rel="alternate" type="text/plain" title="LLMs Full Documentation" href="{{ url('/llms-full.txt') }}">
        <link rel="sitemap" type="application/xml" title="Sitemap" href="{{ url('/sitemap.xml') }}">
        <meta head-key="og:type" property="og:type" content="website">
        <meta head-key="og:site_name" property="og:site_name" content="{{ $appName }}">
        <meta head-key="og:locale" property="og:locale" content="id_ID">
        <meta head-key="og:title" property="og:title" content="{{ $appName }} — {{ $appTagline }}">
        <meta head-key="og:description" property="og:description" content="{{ $defaultDescription }}">
        <meta head-key="og:url" property="og:url" content="{{ $canonicalUrl }}">
        @if($logoUrl)
            <meta head-key="og:image" property="og:image" content="{{ $logoUrl }}">
        @endif
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">
        <meta head-key="twitter:title" name="twitter:title" content="{{ $appName }} — {{ $appTagline }}">
        <meta head-key="twitter:description" name="twitter:description" content="{{ $defaultDescription }}">
    @endif

    @if($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
    @else
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23EA580C'><circle cx='12' cy='12' r='10'/></svg>">
    @endif

    @if(!$isPrivatePath)
        @php
            $baseStructuredData = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'SportsOrganization',
                        '@id' => url('/') . '#organization',
                        'name' => $appName,
                        'description' => $defaultDescription,
                        'url' => url('/'),
                        'sport' => 'Soccer',
                        'logo' => $logoUrl ?: url('/favicon.ico'),
                    ],
                    [
                        '@type' => 'WebSite',
                        '@id' => url('/') . '#website',
                        'url' => url('/'),
                        'name' => $appName,
                        'description' => $appTagline,
                        'inLanguage' => 'id-ID',
                        'publisher' => [
                            '@id' => url('/') . '#organization',
                        ],
                        'potentialAction' => [
                            '@type' => 'SearchAction',
                            'target' => [
                                '@type' => 'EntryPoint',
                                'urlTemplate' => url('/') . '?search={search_term_string}',
                            ],
                            'query-input' => 'required name=search_term_string',
                        ],
                    ],
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($baseStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased text-slate-900 selection:bg-orange-600 selection:text-white bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-orange-100/35 via-slate-50/90 to-slate-100/70 min-h-screen">
    @inertia
</body>
</html>
