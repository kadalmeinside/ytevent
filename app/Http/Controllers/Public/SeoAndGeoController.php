<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SystemSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class SeoAndGeoController extends Controller
{
    /**
     * Menghasilkan robots.txt dinamis yang menyembunyikan area admin, manager, & media privat,
     * serta mengizinkan Search Engine & AI Generative Engine pada katalog publik.
     */
    public function robots(): Response
    {
        $sitemapUrl = url('/sitemap.xml');

        $lines = [
            '# Robots.txt - Multi-Event Football Tournament & League Platform',
            'User-agent: *',
            'Allow: /',
            'Allow: /events/',
            'Allow: /llms.txt',
            'Allow: /llms-full.txt',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /admin/login',
            'Disallow: /manager',
            'Disallow: /manager/',
            'Disallow: /private-media/',
            'Disallow: /verify/player/',
            'Disallow: /verify/official/',
            '',
            '# Explicit AI Search & Generative Engine Crawlers (GEO)',
            'User-agent: GPTBot',
            'Allow: /',
            'Allow: /events/',
            'Allow: /llms.txt',
            'Allow: /llms-full.txt',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /manager',
            'Disallow: /manager/',
            'Disallow: /private-media/',
            'Disallow: /verify/player/',
            'Disallow: /verify/official/',
            '',
            'User-agent: OAI-SearchBot',
            'Allow: /',
            'Allow: /events/',
            'Allow: /llms.txt',
            'Allow: /llms-full.txt',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /manager',
            'Disallow: /manager/',
            '',
            'User-agent: PerplexityBot',
            'Allow: /',
            'Allow: /events/',
            'Allow: /llms.txt',
            'Allow: /llms-full.txt',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /manager',
            'Disallow: /manager/',
            '',
            'User-agent: ClaudeBot',
            'Allow: /',
            'Allow: /events/',
            'Allow: /llms.txt',
            'Allow: /llms-full.txt',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /manager',
            'Disallow: /manager/',
            '',
            'User-agent: Google-Extended',
            'Allow: /',
            'Allow: /events/',
            'Allow: /llms.txt',
            'Allow: /llms-full.txt',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /manager',
            'Disallow: /manager/',
            '',
            "Sitemap: {$sitemapUrl}",
        ];

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * Menghasilkan XML Sitemap standar mesin pencari (hanya halaman publik & event non-draft).
     */
    public function sitemap(): Response
    {
        $events = Event::query()
            ->where('status', '!=', 'draft')
            ->orderByDesc('updated_at')
            ->get();

        $urls = [
            [
                'loc' => route('home'),
                'lastmod' => ($events->first()?->updated_at ?? now())->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => route('login'),
                'lastmod' => now()->startOfDay()->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ],
        ];

        foreach ($events as $event) {
            $urls[] = [
                'loc' => route('events.show', ['event' => $event->slug]),
                'lastmod' => ($event->updated_at ?? now())->toAtomString(),
                'changefreq' => in_array($event->status, ['open', 'ongoing'], true) ? 'daily' : 'weekly',
                'priority' => $event->status === 'open' ? '0.9' : '0.7',
            ];

            if ($event->canAcceptRegistration()) {
                $urls[] = [
                    'loc' => route('events.register', ['event' => $event->slug]),
                    'lastmod' => ($event->updated_at ?? now())->toAtomString(),
                    'changefreq' => 'daily',
                    'priority' => '0.8',
                ];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $item) {
            $loc = htmlspecialchars($item['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $lastmod = htmlspecialchars($item['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $changefreq = htmlspecialchars($item['changefreq'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $priority = htmlspecialchars($item['priority'], ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $xml .= "  <url>\n";
            $xml .= "    <loc>{$loc}</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * Standar GEO (Generative Engine Optimization) /llms.txt (ringkas) sesuai spesifikasi llmstxt.org.
     */
    public function llmsTxt(): Response
    {
        $appName = (string) SystemSetting::get('app_name', config('app.name', 'Persija Development'));
        $tagline = (string) SystemSetting::get('app_tagline', 'Portal Resmi Pendaftaran Turnamen & Liga Sepak Bola');

        $events = Event::query()
            ->where('status', '!=', 'draft')
            ->withOccupiedSlotsCount()
            ->orderByDesc('match_start_date')
            ->get();

        $md = [];
        $md[] = "# {$appName}";
        $md[] = '';
        $md[] = "> {$tagline}. Platform resmi pendaftaran tim turnamen dan liga sepak bola dengan reservasi slot kuota real-time, verifikasi pembayaran, screening berkas kelayakan usia pemain (Akta Kelahiran, KK, KIA, Rapor/Ijazah), dan penerbitan ID Card akreditasi ber-QR Code.";
        $md[] = '';
        $md[] = '## Ringkasan Platform & Alur Pendaftaran';
        $md[] = '- **Katalog Kompetisi Publik**: Calon peserta dapat melihat daftar turnamen/liga sepak bola berdasarkan kategori usia (U-10, U-12, U-15, U-17, Senior) dan sisa kuota slot tim secara real-time.';
        $md[] = '- **Tahap 1 (Reservasi Slot & Pembayaran)**: Pendaftaran tim langsung menahan 1 slot kuota sementara selama batas waktu pembayaran.';
        $md[] = '- **Tahap 2 & 3 (Input Skuad Pemain, Dokumen & Ofisial)**: Setelah pembayaran lunas, Manajer Tim menginput daftar pemain sesuai batas kelahiran usia, memenuhi minimal Penjaga Gawang (GK), mengunggah dokumen identitas asli, serta mendaftarkan Pelatih Kepala & Ofisial.';
        $md[] = '- **Tahap 4 & 5 (Screening Panitia & Kunci Skuad Final)**: Panitia memverifikasi keabsahan berkas setiap pemain. Setelah 100% pemain dinyatakan SAH, Manajer Tim mengunci skuad final untuk mengunduh Album Roster Wasit (PDF) dan ID Card Lanyard (PDF).';
        $md[] = '';
        $md[] = '## Daftar Kompetisi Aktif & Terpublikasi';

        if ($events->isEmpty()) {
            $md[] = '- Saat ini belum ada kompetisi aktif yang dipublikasikan.';
        } else {
            foreach ($events as $event) {
                $url = route('events.show', ['event' => $event->slug]);
                $statusLabel = Event::STATUSES[$event->status] ?? $event->status;
                $fee = 'Rp ' . number_format((float) $event->registration_fee, 0, ',', '.');
                $occupied = $event->occupiedSlotsCount();
                $available = max(0, $event->max_teams - $occupied);
                $md[] = "- [{$event->name} ({$event->age_category})]({$url}): Lokasi di {$event->venue_name}, {$event->city}. Format: " . (Event::FORMATS[$event->format] ?? $event->format) . ". Status: {$statusLabel}. Kuota: {$occupied}/{$event->max_teams} tim (Sisa {$available} slot). Biaya pendaftaran: {$fee}.";
            }
        }

        $md[] = '';
        $md[] = '## Tautan Penting';
        $md[] = '- [Katalog Kompetisi Utama](' . route('home') . '): Halaman utama daftar turnamen dan liga sepak bola.';
        $md[] = '- [Detail Konteks Lengkap untuk AI (/llms-full.txt)](' . url('/llms-full.txt') . '): Spesifikasi lengkap seluruh kompetisi, syarat usia, dokumen wajib, dan regulasi.';
        $md[] = '- [Sitemap XML](' . url('/sitemap.xml') . '): Peta situs terstruktur untuk mesin pencari.';

        return response(implode("\n", $md) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * Standar GEO /llms-full.txt: Konteks lengkap seluruh turnamen untuk AI Generative Engines.
     */
    public function llmsFullTxt(): Response
    {
        $appName = (string) SystemSetting::get('app_name', config('app.name', 'Persija Development'));
        $tagline = (string) SystemSetting::get('app_tagline', 'Portal Resmi Pendaftaran Turnamen & Liga Sepak Bola');

        $events = Event::query()
            ->where('status', '!=', 'draft')
            ->withOccupiedSlotsCount()
            ->orderByDesc('match_start_date')
            ->get();

        $md = [];
        $md[] = "# {$appName} — Dokumentasi Lengkap Kompetisi & Regulasi (AI / LLM Context)";
        $md[] = '';
        $md[] = "> {$tagline}";
        $md[] = '';

        foreach ($events as $event) {
            $occupied = $event->occupiedSlotsCount();
            $available = max(0, $event->max_teams - $occupied);
            $fee = 'Rp ' . number_format((float) $event->registration_fee, 0, ',', '.');
            $docs = collect($event->required_documents ?? [])
                ->map(fn (string $type) => Event::documentLabel($type))
                ->implode(', ');
            $minGk = (int) ($event->min_gk_per_team ?? 2);
            $coachRule = ($event->show_coach_license ?? true)
                ? (($event->require_coach_license ?? false) ? 'Wajib mencantumkan nomor lisensi pelatih kepala' : 'Lisensi pelatih kepala bersifat opsional')
                : 'Tidak memerlukan input lisensi pelatih';

            $cleanDesc = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $event->short_description))));

            $md[] = "## {$event->name} ({$event->age_category})";
            $md[] = '- **URL Resmi**: ' . route('events.show', ['event' => $event->slug]);
            if ($event->canAcceptRegistration()) {
                $md[] = '- **URL Pendaftaran Tim**: ' . route('events.register', ['event' => $event->slug]);
            }
            $md[] = '- **Format Kompetisi**: ' . (Event::FORMATS[$event->format] ?? $event->format);
            $md[] = '- **Status Pendaftaran**: ' . (Event::STATUSES[$event->status] ?? $event->status);
            $md[] = "- **Stadion / Lokasi**: {$event->venue_name}, {$event->city}";
            if ($event->venue_maps_url) {
                $md[] = "- **Google Maps Lokasi**: {$event->venue_maps_url}";
            }
            $md[] = '- **Tanggal Pelaksanaan**: ' . ($event->match_start_date?->format('Y-m-d') ?? 'TBA') . ' s/d ' . ($event->match_end_date?->format('Y-m-d') ?? 'TBA');
            $md[] = '- **Batas Tanggal Lahir Pemain Sah**: ' . ($event->min_birth_date?->format('Y-m-d') ?? 'Bebas') . ' s/d ' . ($event->max_birth_date?->format('Y-m-d') ?? 'Bebas');
            $md[] = "- **Kuota Skuad Pemain per Tim**: Minimal {$event->min_players_per_team} pemain, Maksimal {$event->max_players_per_team} pemain (Wajib minimal {$minGk} Penjaga Gawang / GK).";
            $md[] = "- **Kuota Ofisial per Tim**: Maksimal {$event->max_officials_per_team} ofisial ({$coachRule}).";
            $md[] = "- **Dokumen Screening Wajib**: Pas Foto Resmi, {$docs}.";
            $md[] = "- **Kapasitas Tim**: {$occupied} terisi dari maksimal {$event->max_teams} tim (Sisa {$available} slot).";
            $md[] = "- **Biaya Pendaftaran**: {$fee}";
            if ($event->prize_info) {
                $md[] = "- **Hadiah & Penghargaan**: {$event->prize_info}";
            }
            if ($cleanDesc) {
                $md[] = "- **Deskripsi**: {$cleanDesc}";
            }
            $md[] = '';
        }

        return response(implode("\n", $md) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}

